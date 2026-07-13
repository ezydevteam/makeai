<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Http\Controllers\User;

use Addons\AiImagePro\Exceptions\ImageOperationException;
use Addons\AiImagePro\Http\Resources\AssetResource;
use Addons\AiImagePro\Models\AipAsset;
use Addons\AiImagePro\Services\AssetService;
use Addons\AiImagePro\Services\CreditService;
use Addons\AiImagePro\Services\ImageAccessService;
use Addons\AiImagePro\Services\LocalImageService;
use Addons\AiImagePro\Services\OperationRegistry;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The synchronous half of the toolbox: the local GD operations (resize, crop,
 * rotate, compress, convert, watermark, text, colour). They queue nothing and
 * produce a `derived` asset in the same request. They are free by default, but the
 * admin may put a flat credit price on any of them (0/blank keeps it free).
 *
 * These deliberately do NOT go through RunOperationRequest — that request class
 * exists to build a *job*, and it rejects local-tier ops with a 422 for exactly
 * that reason. The gate is applied here directly through ImageAccessService, and
 * the params are validated inline because their shape is per-operation and owned
 * by LocalImageService, not by a shared job schema.
 */
class ToolController extends Controller
{
    public function __construct(
        private readonly OperationRegistry $registry,
        private readonly ImageAccessService $access,
        private readonly LocalImageService $localImages,
        private readonly AssetService $assets,
        private readonly CreditService $credits,
    ) {
    }

    public function run(Request $request, string $operation): JsonResponse
    {
        $user = $this->currentUser($request);
        $ip = $request->ip();

        $definition = $this->registry->get($operation);

        if ($definition === null) {
            return $this->error(translate('Unknown image operation.'), 404);
        }

        // A queued op sent here by mistake belongs on the /ops endpoint.
        if ($this->registry->tier($operation) !== OperationRegistry::TIER_LOCAL) {
            return $this->error(translate('This operation is queued and must be sent to the operations endpoint.'), 422);
        }

        // Access, availability and daily-limit gate. Throws a JSON HttpResponseException.
        $this->access->assertCanRun($operation, $user, $ip);

        $request->validate([
            'asset_ulid' => ['required', 'string', 'size:26'],
            'params' => ['nullable', 'array'],
        ]);

        $asset = AipAsset::where('ulid', $request->input('asset_ulid'))->first();

        if (! $asset || ! $asset->ownedBy($user, $ip)) {
            abort(403);
        }

        $this->access->assertWithinStorageQuota($user);

        // A local tool is free by default, but the admin may have priced it (0/blank
        // keeps it free). Guests can't be charged, so they run within their daily cap
        // only. Check affordability up front; charge only after the result is stored.
        $cost = $this->registry->credits($operation);
        $this->credits->assertCanAffordFlat($user, $cost);

        $params = (array) $request->input('params', []);

        $source = null;
        $temp = null;

        try {
            // Resolve to a real local path (streams a temp copy when on a cloud disk).
            $source = $this->assets->localCopy($asset);

            $temp = $this->apply($operation, $source, $params);

            $result = $this->assets->storeFromFile($temp, [
                'user_id' => $user?->id,
                'guest_ip' => $user ? null : $ip,
                'source' => 'derived',
                'operation' => $operation,
                'parent_id' => $asset->id,
                'folder_id' => $asset->folder_id,
                'expires_at' => $this->access->expiresAtFor($user),
            ]);
        } catch (ImageOperationException $e) {
            return $this->error($e->getMessage(), 422);
        } finally {
            if ($temp !== null && is_file($temp)) {
                @unlink($temp);
            }
            if ($source !== null) {
                $this->assets->releaseLocalCopy($asset, $source);
            }
        }

        // The op ran and the asset is stored — charge now (never before success, so
        // a failed tool costs nothing and needs no refund path). Mode-aware helper.
        if ($cost > 0 && $user) {
            deduct_credits($user->id, (float) $cost, 'AI Image Pro: ' . $operation);
        }

        $result->loadMissing('parent:id,ulid');

        return response()->json([
            'success' => true,
            'asset' => new AssetResource($result),
        ]);
    }

    /**
     * Route the operation to its GD method. The registry guarantees this is a
     * local-tier op; an operation slug with no handler is a programming error, not
     * a user error, so it fails as an ImageOperationException.
     *
     * @param  array<string, mixed>  $params
     *
     * @throws ImageOperationException
     */
    private function apply(string $operation, string $source, array $params): string
    {
        return match ($operation) {
            'resize' => $this->localImages->resize($source, $params),
            'crop' => $this->localImages->crop($source, $params),
            'rotate' => $this->localImages->rotate($source, $params),
            'compress' => $this->localImages->compress($source, $params),
            'convert' => $this->localImages->convert($source, $params),
            'watermark' => $this->localImages->watermark($source, $params),
            'text_overlay' => $this->localImages->textOverlay($source, $params),
            'color_correction' => $this->localImages->colorCorrection($source, $params),
            default => throw new ImageOperationException(translate('This tool is not available.')),
        };
    }

    private function currentUser(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
