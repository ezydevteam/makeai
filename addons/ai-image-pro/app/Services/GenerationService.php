<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Services;

use Addons\AiImagePro\Exceptions\ImageOperationException;
use Addons\AiImagePro\Models\AipAsset;
use Addons\AiImagePro\Models\AipJob;
use Addons\AiImagePro\Services\Providers\FalClient;
use Addons\AiImagePro\Services\Providers\IdeogramClient;
use Addons\AiImagePro\Services\Providers\ReplicateClient;
use Addons\AiImagePro\Services\Providers\StabilityClient;
use App\Models\AiModel;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\AI\TokenGuard;
use App\Services\ContentModerationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Image;

/**
 * The `generate` tier: text-to-image, variations and prompt edits.
 *
 * Two engines sit behind one entry point. Models served by the core AiManager
 * (OpenAI, Gemini, …) go through `AiService::generateImage()`, which already runs
 * the media credit path (before/afterMedia) and the moderation gate itself — this
 * service just persists what comes back. Every other model (Stability, Replicate,
 * fal, Ideogram) is called through the addon's own client here, and this service
 * owns the moderation-before-billing discipline: an output that fails moderation
 * is deleted and the job is failed *before* `afterMedia` runs, so a blocked image
 * is never billed.
 */
class GenerationService
{
    public function __construct(
        private ModelCatalog $catalog,
        private AssetService $assets,
        private ImageAccessService $access,
        private AiService $ai,
        private StabilityClient $stability,
        private ReplicateClient $replicate,
        private FalClient $fal,
        private IdeogramClient $ideogram,
    ) {
    }

    /**
     * @return array<int, AipAsset>
     *
     * @throws ImageOperationException
     */
    public function run(AipJob $job): array
    {
        $model = $this->catalog->resolve($job->model);
        $user = $job->user;
        $count = max(1, (int) ($job->batch_size ?: 1));
        $params = is_array($job->params) ? $job->params : [];
        $prompt = trim((string) ($params['prompt'] ?? ''));
        $provider = strtolower(trim((string) $model->provider));

        if ($this->catalog->isCoreProvider($provider)) {
            return $this->runCore($job, $model, $user, $prompt, $params, $count);
        }

        return $this->runAddon($job, $model, $user, $prompt, $params, $count);
    }

    /**
     * Core AiManager providers. `AiService::generateImage()` bills and moderates
     * internally per call, so we loop it once per requested image (the image
     * gateway produces one image per call) and only persist the results.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, AipAsset>
     */
    private function runCore(AipJob $job, AiModel $model, ?User $user, string $prompt, array $params, int $count): array
    {
        $attachments = [];
        $input = $job->inputAsset;

        if ($input) {
            $attachments[] = Image::fromStorage($input->path, $input->disk ?: 'public');
        }

        $size = $this->resolveSize($model, $params);
        $quality = isset($params['quality']) ? (string) $params['quality'] : null;
        $providerModel = $this->catalog->providerModel($model);

        $assets = [];

        for ($i = 0; $i < $count; $i++) {
            try {
                $result = $this->ai->generateImage(
                    $prompt,
                    strtolower(trim((string) $model->provider)),
                    $providerModel,
                    $size,
                    $quality,
                    $user,
                    $attachments,
                    1,
                );
            } catch (\Throwable $e) {
                // First image failed → nothing produced, nothing to keep: surface
                // it so the job fails cleanly. If earlier images already succeeded
                // (and were billed by AiService), keep them rather than throwing
                // away paid output — there is no media-refund path to undo them.
                if ($assets === []) {
                    throw new ImageOperationException($e->getMessage(), 0, $e);
                }

                Log::warning('[ai-image-pro] Partial generation batch', [
                    'job' => $job->ulid,
                    'produced' => count($assets),
                    'error' => $e->getMessage(),
                ]);
                break;
            }

            foreach (($result['images'] ?? []) as $image) {
                $attributes = $this->assetAttributes(
                    $job,
                    $model,
                    $params,
                    is_string($image['revised_prompt'] ?? null) ? $image['revised_prompt'] : $prompt,
                );

                // Core providers return inline base64 (via GeneratedImage), not a hosted
                // URL — so decode and store the bytes directly. `url` is only ever a data:
                // URI here; fall back to it (and to a genuine remote URL, should a future
                // provider return one) only when there is no base64 to use.
                $b64 = is_string($image['b64'] ?? null) ? $image['b64'] : null;
                $url = $image['url'] ?? null;

                if ($b64 !== null && $b64 !== '') {
                    $bytes = base64_decode($b64, true);

                    if ($bytes !== false && $bytes !== '') {
                        $assets[] = $this->assets->storeBinary($bytes, $attributes);

                        continue;
                    }
                }

                if (is_string($url) && $url !== '' && ! str_starts_with($url, 'data:')) {
                    $assets[] = $this->assets->storeFromUrl($url, $attributes);
                }
            }
        }

        if ($assets === []) {
            throw new ImageOperationException(translate('The model returned no image.'));
        }

        return $assets;
    }

    /**
     * Addon-hosted generation models. The client returns raw bytes; we persist,
     * then moderate before billing, then bill once for the whole batch.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, AipAsset>
     */
    private function runAddon(AipJob $job, AiModel $model, ?User $user, string $prompt, array $params, int $count): array
    {
        $provider = strtolower(trim((string) $model->provider));
        $options = $this->addonOptions($model, $params);

        // Image-to-image reference (variations / prompt edit) where the input
        // exists — only clients that accept a reference will use it.
        $input = $job->inputAsset;
        if ($input) {
            // Read the reference bytes straight from whichever disk holds the asset
            // (local or cloud). Best-effort: only clients that accept a reference use it.
            $binary = Storage::disk($input->disk ?: 'public')->get($input->path);
            if (is_string($binary) && $binary !== '') {
                $options['image'] = $binary;
            }
        }

        $binaries = [];
        for ($i = 0; $i < $count && count($binaries) < $count; $i++) {
            foreach ($this->callGenerator($provider, $prompt, $options) as $bytes) {
                $binaries[] = $bytes;

                if (count($binaries) >= $count) {
                    break;
                }
            }
        }

        if ($binaries === []) {
            throw new ImageOperationException(translate('The model returned no image.'));
        }

        $assets = [];
        foreach ($binaries as $bytes) {
            $assets[] = $this->assets->storeBinary($bytes, $this->assetAttributes($job, $model, $params, $prompt));
        }

        $this->assertModerated($assets);

        // Only bill once every output has passed moderation — nothing is charged
        // for a batch that was blocked or that failed before this point.
        TokenGuard::afterMedia(
            $user,
            'image',
            $model->slug,
            $provider,
            count($assets),
            ['type' => 'image_generation', 'tool_slug' => OperationRegistry::SLUG],
        );

        return $assets;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     *
     * @throws ImageOperationException
     */
    private function callGenerator(string $provider, string $prompt, array $options): array
    {
        return match ($provider) {
            'stability' => $this->stability->generate($prompt, $options),
            'replicate' => $this->replicate->generate($prompt, $options),
            'fal', 'fal_ai', 'falai' => $this->fal->generate($prompt, $options),
            'ideogram' => $this->ideogram->generate($prompt, $options),
            default => throw new ImageOperationException(translate('The selected image model is not available.')),
        };
    }

    /**
     * Delete and reject a batch the moment any output violates the safety policy.
     * Runs before billing so a blocked image is never charged.
     *
     * @param  array<int, AipAsset>  $assets
     *
     * @throws ImageOperationException
     */
    private function assertModerated(array $assets): void
    {
        $moderation = ContentModerationService::fromSettings();

        foreach ($assets as $asset) {
            if ($moderation->imageViolates($asset->url, OperationRegistry::SLUG)) {
                foreach ($assets as $created) {
                    $this->assets->delete($created);
                }

                throw new ImageOperationException(translate('This image was blocked by content safety filters.'));
            }
        }
    }

    /**
     * Shared attributes for every generated asset.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function assetAttributes(AipJob $job, AiModel $model, array $params, string $prompt): array
    {
        return [
            'user_id' => $job->user_id,
            'guest_ip' => $job->guest_ip,
            'job_id' => $job->id,
            'parent_id' => $job->input_asset_id,
            'source' => $job->input_asset_id ? 'derived' : 'generated',
            'operation' => $job->operation,
            'prompt' => $prompt !== '' ? $prompt : null,
            'negative_prompt' => ($params['negative_prompt'] ?? null) ?: null,
            'model' => $model->slug,
            'provider' => strtolower(trim((string) $model->provider)),
            'seed' => isset($params['seed']) && $params['seed'] !== '' ? (string) $params['seed'] : null,
            'params' => $params,
            'expires_at' => $this->access->expiresAtFor($job->user),
        ];
    }

    /**
     * Options common to the addon generation clients, drawn from the model's
     * declared capabilities and the request params.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function addonOptions(AiModel $model, array $params): array
    {
        $options = [
            'provider_model' => $this->catalog->providerModel($model),
            'version' => $this->catalog->replicateVersion($model),
            'num_images' => 1,
        ];

        if ($this->catalog->supportsCapability($model, 'negative_prompt') && ! empty($params['negative_prompt'])) {
            $options['negative_prompt'] = (string) $params['negative_prompt'];
        }

        if ($this->catalog->supportsCapability($model, 'seed') && isset($params['seed']) && $params['seed'] !== '') {
            $options['seed'] = (int) $params['seed'];
        }

        if (! empty($params['aspect_ratio'])) {
            $options['aspect_ratio'] = (string) $params['aspect_ratio'];
        }

        [$width, $height] = $this->dimensions($params);
        if ($width && $height) {
            $options['width'] = $width;
            $options['height'] = $height;
        }

        return array_filter($options, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * The `size` argument the core image provider expects — and providers disagree on
     * what that means. OpenAI wants pixels ("1024x1024"); Google/Gemini wants an
     * aspect-ratio string ("1:1", "16:9") and 400s on a pixel size. So resolve per the
     * provider's own convention rather than assuming one shape.
     *
     * @param  array<string, mixed>  $params
     */
    private function resolveSize(AiModel $model, array $params): ?string
    {
        if ($this->providerWantsAspectRatio($model)) {
            return $this->resolveAspectRatio($params);
        }

        [$width, $height] = $this->dimensions($params);

        if ($width && $height) {
            return "{$width}x{$height}";
        }

        $sizes = $this->catalog->sizes($model);

        return $sizes[0] ?? null;
    }

    private function providerWantsAspectRatio(AiModel $model): bool
    {
        return in_array(strtolower(trim((string) $model->provider)), ['google', 'gemini'], true);
    }

    /**
     * The aspect-ratio string for a provider that expects one. Prefers the ratio the
     * user picked (the `aspect` chip), derives it from explicit width/height otherwise,
     * and only ever returns a value the provider actually supports.
     *
     * @param  array<string, mixed>  $params
     */
    private function resolveAspectRatio(array $params): ?string
    {
        // Gemini's documented set.
        $supported = ['1:1', '2:3', '3:2', '3:4', '4:3', '9:16', '16:9', '21:9'];

        $aspect = trim((string) ($params['aspect'] ?? ''));
        if ($aspect !== '' && in_array($aspect, $supported, true)) {
            return $aspect;
        }

        [$width, $height] = $this->dimensions($params);
        if ($width > 0 && $height > 0) {
            $divisor = $this->gcd($width, $height);
            $candidate = ($width / $divisor) . ':' . ($height / $divisor);

            if (in_array($candidate, $supported, true)) {
                return $candidate;
            }
        }

        return '1:1';
    }

    private function gcd(int $a, int $b): int
    {
        return $b === 0 ? max(1, $a) : $this->gcd($b, $a % $b);
    }

    /**
     * Resolve pixel dimensions from an explicit size param or a "WxH" string.
     *
     * @param  array<string, mixed>  $params
     * @return array{0: int, 1: int}
     */
    private function dimensions(array $params): array
    {
        $size = $params['size'] ?? null;

        if (is_string($size) && preg_match('/^(\d+)\s*x\s*(\d+)$/i', trim($size), $m) === 1) {
            return [(int) $m[1], (int) $m[2]];
        }

        if (isset($params['width'], $params['height'])) {
            return [(int) $params['width'], (int) $params['height']];
        }

        return [0, 0];
    }
}
