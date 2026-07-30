<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Http\Controllers\User;

use Addons\AiImagePro\Exceptions\ImageOperationException;
use Addons\AiImagePro\Http\Resources\AssetResource;
use Addons\AiImagePro\Models\AipAsset;
use Addons\AiImagePro\Models\AipFolder;
use Addons\AiImagePro\Services\AssetService;
use Addons\AiImagePro\Services\ImageAccessService;
use Addons\AiImagePro\Services\LocalImageService;
use Addons\AiImagePro\Services\OperationRegistry;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Single-asset actions plus the library bulk action. Every entry point re-checks
 * ownership through `AipAsset::ownedBy()` — the guest routes have no auth
 * middleware, so a guest owns an asset only by matching guest-IP, and a 403 is the
 * only correct answer for anything else.
 */
class AssetController extends Controller
{
    public function __construct(
        private readonly AssetService $assets,
        private readonly ImageAccessService $access,
        private readonly LocalImageService $localImages,
    ) {
    }

    public function show(Request $request, AipAsset $asset): JsonResponse
    {
        $this->authorizeAsset($asset, $request);

        $asset->loadMissing('parent:id,ulid');

        return response()->json([
            'success' => true,
            'asset' => new AssetResource($asset),
        ]);
    }

    /**
     * Download the original file, watermarked on the fly for users the admin's
     * watermark policy applies to (free users, when a watermark text is set). The
     * stored asset is never mutated — the mark is applied to a throwaway copy that
     * is deleted after the response is sent.
     */
    public function download(Request $request, AipAsset $asset): BinaryFileResponse
    {
        $user = $this->authorizeAsset($asset, $request);

        try {
            $absolutePath = $this->assets->localCopy($asset);
        } catch (\Throwable) {
            abort(404, translate('This image file is no longer available.'));
        }

        // On a cloud disk localCopy() streamed a throwaway temp file; it must be
        // cleaned up after the response is sent. On local it is the real stored file
        // and must never be deleted.
        $isTempCopy = ! $this->assets->usesLocalDisk($asset);

        $extension = pathinfo($asset->path, PATHINFO_EXTENSION) ?: 'png';
        $filename = 'ai-image-' . $asset->ulid . '.' . $extension;

        if ($this->access->shouldWatermark($user)) {
            $watermarked = $this->watermarkedCopy($absolutePath);

            if ($watermarked !== null) {
                if ($isTempCopy && is_file($absolutePath)) {
                    @unlink($absolutePath);
                }

                return response()
                    ->download($watermarked, $filename)
                    ->deleteFileAfterSend(true);
            }
        }

        return response()
            ->download($absolutePath, $filename)
            ->deleteFileAfterSend($isTempCopy);
    }

    public function toggleFavorite(Request $request, AipAsset $asset): JsonResponse
    {
        $this->authorizeAsset($asset, $request);

        $asset->is_favorite = ! $asset->is_favorite;
        $asset->save();

        return response()->json([
            'success' => true,
            'is_favorite' => $asset->is_favorite,
        ]);
    }

    public function destroy(Request $request, AipAsset $asset): JsonResponse
    {
        $this->authorizeAsset($asset, $request);

        $this->assets->delete($asset);

        return response()->json(['success' => true]);
    }

    /**
     * Library bulk action. This route sits in the authenticated group, so ownership
     * is scoped by `user_id` in the query itself — a ulid the user does not own is
     * simply never selected, and cannot be affected.
     */
    public function bulk(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:delete,favorite,unfavorite,move'],
            'ulids' => ['required', 'array', 'min:1'],
            'ulids.*' => ['string', 'size:26'],
            'folder_id' => ['nullable', 'integer', 'exists:aip_folders,id'],
        ]);

        $folderId = null;
        if ($validated['action'] === 'move' && ! empty($validated['folder_id'])) {
            $folder = AipFolder::where('id', $validated['folder_id'])
                ->where('user_id', $user->id)
                ->first();

            if (! $folder) {
                return response()->json([
                    'success' => false,
                    'message' => translate('That folder is not available.'),
                ], 422);
            }

            $folderId = $folder->id;
        }

        $assets = AipAsset::where('user_id', $user->id)
            ->whereIn('ulid', $validated['ulids'])
            ->get();

        $affected = 0;

        foreach ($assets as $asset) {
            match ($validated['action']) {
                'delete' => $this->assets->delete($asset),
                'favorite' => $asset->update(['is_favorite' => true]),
                'unfavorite' => $asset->update(['is_favorite' => false]),
                'move' => $asset->update(['folder_id' => $folderId]),
            };

            $affected++;
        }

        return response()->json([
            'success' => true,
            'affected' => $affected,
        ]);
    }

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403
     */
    private function authorizeAsset(AipAsset $asset, Request $request): ?User
    {
        $user = $request->user();
        $user = $user instanceof User ? $user : null;

        if (! $asset->ownedBy($user, $request->ip())) {
            abort(403);
        }

        return $user;
    }

    /**
     * Produce a watermarked temp copy, or null if the mark could not be applied —
     * in which case the caller falls back to serving the original rather than
     * failing the download.
     */
    private function watermarkedCopy(string $absolutePath): ?string
    {
        $position = (string) addon_setting(OperationRegistry::SLUG, 'watermark_position', 'bottom-right');
        $opacity = (int) addon_setting(OperationRegistry::SLUG, 'watermark_opacity', 60);

        // A logo, when set, replaces the text mark entirely.
        $logoPath = trim((string) addon_setting(OperationRegistry::SLUG, 'watermark_logo_path', ''));

        if ($logoPath !== '') {
            // The logo lives on the public disk, which may be a cloud bucket — resolve
            // it to a real local path (streaming a temp copy when on cloud).
            $logo = $this->publicDiskLocalCopy($logoPath);

            if ($logo !== null) {
                [$logoAbsolute, $logoIsTemp] = $logo;

                try {
                    return $this->localImages->watermarkLogo($absolutePath, $logoAbsolute, [
                        'position' => $position,
                        'opacity' => $opacity,
                    ]);
                } catch (ImageOperationException) {
                    return null;
                } finally {
                    if ($logoIsTemp && is_file($logoAbsolute)) {
                        @unlink($logoAbsolute);
                    }
                }
            }
        }

        $text = trim((string) addon_setting(OperationRegistry::SLUG, 'watermark_text', ''));

        if ($text === '') {
            return null;
        }

        try {
            return $this->localImages->watermark($absolutePath, [
                'text' => $text,
                'position' => $position,
                'opacity' => $opacity,
            ]);
        } catch (ImageOperationException) {
            return null;
        }
    }

    /**
     * Resolve a public-disk file to a real local path GD can read.
     * Returns [absolutePath, isTempCopy] or null if the file is missing/unreadable.
     * On a cloud disk the object is streamed to a temp file (isTempCopy = true) that
     * the caller must unlink; on local it is the real file (isTempCopy = false).
     *
     * @return array{0: string, 1: bool}|null
     */
    private function publicDiskLocalCopy(string $path): ?array
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        if (config('filesystems.disks.public.driver') === 'local') {
            $absolute = $disk->path($path);

            return is_file($absolute) ? [$absolute, false] : null;
        }

        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $temp = tempnam(sys_get_temp_dir(), 'aipwm_');
        if ($temp === false) {
            fclose($stream);

            return null;
        }
        $target = $extension !== '' ? $temp . '.' . $extension : $temp;
        if ($target !== $temp) {
            @rename($temp, $target);
        }

        $out = @fopen($target, 'wb');
        if ($out === false) {
            fclose($stream);
            @unlink($target);

            return null;
        }

        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);

        return [$target, true];
    }
}
