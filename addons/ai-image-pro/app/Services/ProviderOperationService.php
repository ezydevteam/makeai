<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Services;

use Addons\AiImagePro\Exceptions\ImageOperationException;
use Addons\AiImagePro\Models\AipAsset;
use Addons\AiImagePro\Models\AipJob;
use Addons\AiImagePro\Services\Providers\ClipdropClient;
use Addons\AiImagePro\Services\Providers\RemoveBgClient;
use Addons\AiImagePro\Services\Providers\ReplicateClient;
use Addons\AiImagePro\Services\Providers\StabilityClient;
use App\Services\ContentModerationService;
use Illuminate\Support\Facades\Storage;

/**
 * The `provider` tier: the queued third-party edits (background remove/replace,
 * upscale, inpaint, object removal, outpaint, style transfer).
 *
 * The engine that runs an operation is whatever the registry resolved for it —
 * never a value baked into this class. Each provider client speaks raw bytes in
 * and raw bytes out; this service reads the input asset's bytes, dispatches to the
 * resolved engine, moderates the result, and hands the bytes to `AssetService`
 * with `parent_id` set to the input so the edit lineage stays reconstructable.
 * Credits for this tier are charged up front and refunded by the job on failure,
 * so a blocked or failed edit simply throws and the job winds the charge back.
 */
class ProviderOperationService
{
    public function __construct(
        private AssetService $assets,
        private OperationRegistry $registry,
        private ImageAccessService $access,
        private StabilityClient $stability,
        private ReplicateClient $replicate,
        private RemoveBgClient $removeBg,
        private ClipdropClient $clipdrop,
    ) {
    }

    /**
     * @return array<int, AipAsset>
     *
     * @throws ImageOperationException
     */
    public function run(AipJob $job): array
    {
        $operation = (string) $job->operation;
        $engine = $this->resolveEngine($job);

        $input = $job->inputAsset;
        if (! $input) {
            throw new ImageOperationException(translate('The source image could not be found.'));
        }

        // Masks and uploaded references are transient, user-private inputs: StudioController
        // stores them on the PRIVATE `local` disk (storePrivate()), never the public one.
        // Reading them from 'public' found nothing, so inpaint / object-remove / outpaint /
        // style-transfer failed on every driver — read them from the disk they're written to.
        $image = $this->bytesOf($input->disk ?: 'public', $input->path, translate('The source image could not be read.'));
        $mask = $job->mask_path ? $this->bytesOf('local', $job->mask_path, translate('The mask image could not be read.')) : null;
        $reference = $job->reference_path ? $this->bytesOf('local', $job->reference_path, translate('The reference image could not be read.')) : null;

        $params = is_array($job->params) ? $job->params : [];
        $prompt = trim((string) ($params['prompt'] ?? ''));

        $binary = $this->dispatch($operation, $engine, $image, $mask, $reference, $prompt, $params);

        $asset = $this->assets->storeBinary($binary, [
            'user_id' => $job->user_id,
            'guest_ip' => $job->guest_ip,
            'job_id' => $job->id,
            'parent_id' => $input->id,
            'source' => 'derived',
            'operation' => $operation,
            'prompt' => $prompt !== '' ? $prompt : null,
            'model' => $job->model,
            'provider' => $engine,
            'params' => $params,
            'expires_at' => $this->access->expiresAtFor($job->user),
        ]);

        // Discard unsafe output before returning it. Failing here throws, which
        // fails the job and refunds the up-front flat charge.
        if (ContentModerationService::fromSettings()->imageViolates($asset->url, OperationRegistry::SLUG)) {
            $this->assets->delete($asset);

            throw new ImageOperationException(translate('This image was blocked by content safety filters.'));
        }

        return [$asset];
    }

    /**
     * @param  array<string, mixed>  $params
     *
     * @throws ImageOperationException
     */
    private function dispatch(string $operation, string $engine, string $image, ?string $mask, ?string $reference, string $prompt, array $params): string
    {
        return match ($operation) {
            'bg_remove' => $this->bgRemove($engine, $image),
            'bg_replace' => $this->bgReplace($engine, $image, $prompt, $params),
            'upscale' => $this->upscale($engine, $image, $params),
            'inpaint' => $this->inpaint($engine, $image, $mask, $prompt, $params),
            'object_remove' => $this->objectRemove($engine, $image, $mask, $params),
            'outpaint' => $this->outpaint($engine, $image, $prompt, $params),
            'style_transfer' => $this->styleTransfer($engine, $image, $reference, $params),
            default => throw new ImageOperationException(translate('This operation is not supported.')),
        };
    }

    private function bgRemove(string $engine, string $image): string
    {
        return match ($engine) {
            'remove_bg' => $this->removeBg->removeBackground($image),
            'clipdrop' => $this->clipdrop->removeBackground($image),
            'replicate' => $this->replicate->removeBackground($image),
            default => throw $this->unsupported($engine),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function bgReplace(string $engine, string $image, string $prompt, array $params): string
    {
        return match ($engine) {
            'stability' => $this->stability->replaceBackground($image, $prompt, $params),
            'clipdrop' => $this->clipdrop->replaceBackground($image, $prompt, $params),
            default => throw $this->unsupported($engine),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function upscale(string $engine, string $image, array $params): string
    {
        return match ($engine) {
            'replicate' => $this->replicate->upscale($image, $params),
            'stability' => $this->stability->upscale($image, $params),
            'clipdrop' => $this->clipdrop->upscale($image, $params),
            default => throw $this->unsupported($engine),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function inpaint(string $engine, string $image, ?string $mask, string $prompt, array $params): string
    {
        $mask = $this->requireMask($mask);

        return match ($engine) {
            'stability' => $this->stability->inpaint($image, $mask, $prompt, $params),
            'replicate' => $this->replicate->sdInpaint($image, $mask, $prompt, $params),
            default => throw $this->unsupported($engine),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function objectRemove(string $engine, string $image, ?string $mask, array $params): string
    {
        $mask = $this->requireMask($mask);

        return match ($engine) {
            'stability' => $this->stability->objectRemove($image, $mask, $params),
            'clipdrop' => $this->clipdrop->removeObject($image, $mask),
            default => throw $this->unsupported($engine),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function outpaint(string $engine, string $image, string $prompt, array $params): string
    {
        $expand = is_array($params['expand'] ?? null) ? $params['expand'] : $params;

        return match ($engine) {
            'stability' => $this->stability->outpaint($image, $expand, $prompt, $params),
            default => throw $this->unsupported($engine),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function styleTransfer(string $engine, string $image, ?string $reference, array $params): string
    {
        if (! is_string($reference) || $reference === '') {
            throw new ImageOperationException(translate('A style reference image is required.'));
        }

        return match ($engine) {
            'stability' => $this->stability->styleTransfer($image, $reference, $params),
            default => throw $this->unsupported($engine),
        };
    }

    /**
     * The engine the registry resolved for this operation. Prefers the value
     * recorded on the job (what the flat charge was priced against) and falls back
     * to the live registry resolution — never a literal provider name.
     */
    private function resolveEngine(AipJob $job): string
    {
        $engine = trim((string) $job->engine);

        return $engine !== '' ? $engine : $this->registry->engine((string) $job->operation);
    }

    private function requireMask(?string $mask): string
    {
        if (! is_string($mask) || $mask === '') {
            throw new ImageOperationException(translate('This operation requires a mask.'));
        }

        return $mask;
    }

    private function bytesOf(string $disk, string $path, string $error): string
    {
        $storage = Storage::disk($disk ?: 'public');

        if (! $storage->exists($path)) {
            throw new ImageOperationException($error);
        }

        $bytes = $storage->get($path);

        if (! is_string($bytes) || $bytes === '') {
            throw new ImageOperationException($error);
        }

        return $bytes;
    }

    private function unsupported(string $engine): ImageOperationException
    {
        return new ImageOperationException(translate(
            'The :engine engine cannot perform this operation.',
            ['engine' => $engine],
        ));
    }
}
