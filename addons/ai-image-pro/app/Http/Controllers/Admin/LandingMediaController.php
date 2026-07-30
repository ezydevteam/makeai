<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Image uploads for the landing page's admin repeaters.
 *
 * The repeaters used to take a bare URL, which meant an operator had to host the
 * image somewhere else first. They now upload here and we hand back a URL, which is
 * still what gets stored in the setting — so the stored shape does not change and an
 * externally-hosted URL an operator pasted before keeps working.
 *
 * Sits behind `admin.permission:addon.aip.settings`, the same gate as the settings
 * screen itself.
 */
class LandingMediaController extends Controller
{
    private const DIRECTORY = 'ai-image-pro/landing';
    private const WATERMARK_DIRECTORY = 'ai-image-pro/watermark';

    public function store(Request $request): JsonResponse
    {
        return $this->upload($request, self::DIRECTORY, ['jpg', 'jpeg', 'png', 'webp', 'avif']);
    }

    /**
     * The watermark logo. PNG-first (transparency is the point of a logo watermark),
     * with WebP allowed too; stored in its own directory so the same confinement
     * rules keep removals from reaching anything else on the disk.
     */
    public function storeWatermark(Request $request): JsonResponse
    {
        return $this->upload($request, self::WATERMARK_DIRECTORY, ['png', 'webp']);
    }

    public function destroyWatermark(Request $request): JsonResponse
    {
        return $this->remove($request, self::WATERMARK_DIRECTORY);
    }

    /**
     * @param  array<int, string>  $mimes
     */
    private function upload(Request $request, string $directory, array $mimes): JsonResponse
    {
        $request->validate([
            // Deliberately strict: this is an admin-authored asset, not a user upload.
            // SVG is excluded — it can carry script.
            'image' => ['required', 'file', 'mimes:' . implode(',', $mimes), 'max:4096'],
        ], [
            'image.mimes' => translate('Use a :types image.', ['types' => strtoupper(implode(', ', $mimes))]),
            'image.max' => translate('The image may not be larger than 4 MB.'),
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('image');

        $name = Str::ulid() . '.' . strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs($directory, $name, 'public');

        return response()->json([
            'success' => true,
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
        ]);
    }

    /**
     * Remove an image the admin replaced or cleared.
     *
     * Accepts either the storage `path` we handed back on upload, or the `url` that is
     * actually stored in the setting. The URL form matters: the admin UI can only
     * remember path-for-url within a single page load, so after a save-and-reload a
     * removal would otherwise leave the file orphaned on disk forever. Resolving the URL
     * back to a path here means removal reclaims the disk whenever the file is one of
     * ours.
     *
     * Both forms are confined to this addon's own directory, so a crafted value cannot
     * reach anything else on the disk — an externally-hosted URL simply resolves to
     * nothing and is a no-op.
     */
    public function destroy(Request $request): JsonResponse
    {
        return $this->remove($request, self::DIRECTORY);
    }

    private function remove(Request $request, string $directory): JsonResponse
    {
        $path = $this->resolveOwnedPath(
            (string) $request->input('path', ''),
            (string) $request->input('url', ''),
            $directory,
        );

        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }

        return response()->json(['success' => true]);
    }

    /**
     * The storage path for a file we own, or null when the input points anywhere else.
     */
    private function resolveOwnedPath(string $path, string $url, string $directory): ?string
    {
        if ($path === '' && $url !== '') {
            // Keep only the path component, so an absolute URL and a relative one both
            // reduce to the same thing, then strip the disk's public prefix (/storage).
            $candidate = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
            $path = preg_replace('#^storage/#', '', $candidate) ?? '';
        }

        $path = trim($path);

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return str_starts_with($path, $directory . '/') ? $path : null;
    }
}
