<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Services;

use Addons\AiImagePro\Exceptions\ImageOperationException;
use Addons\AiImagePro\Models\AipAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The only thing in the addon that writes to the library.
 *
 * Everything else — providers, the GD toolbox, the jobs — produces bytes or a
 * temp file and hands it here. That is deliberate: it means there is exactly one
 * place that decides the path layout, one place that measures an image, and one
 * place that can be trusted to have recorded real `width`/`height`/`bytes`/`mime`
 * (the storage quota, the gallery grid and the retention sweep all read them).
 *
 * Provider URLs are never persisted. Stability, Replicate, fal and OpenAI all
 * hand back short-lived signed URLs; storing one produces a library that quietly
 * turns into broken images within the hour. `storeFromUrl()` downloads first.
 */
class AssetService
{
    private const DISK = 'public';

    /** Path layout: ai-image-pro/{user_id|guest}/{ulid}.{ext} */
    private const ROOT = 'ai-image-pro';

    private const GUEST_DIR = 'guest';

    private const DOWNLOAD_TIMEOUT = 60;

    /** @var array<string, string> mime → file extension */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/gif' => 'gif',
    ];

    /** Attributes a caller may set; everything else about the row is measured, not passed. */
    private const ASSIGNABLE = [
        'user_id', 'guest_ip', 'folder_id', 'job_id', 'parent_id',
        'source', 'operation', 'prompt', 'negative_prompt',
        'model', 'provider', 'seed', 'params', 'is_favorite', 'expires_at',
    ];

    public function __construct(private LocalImageService $images)
    {
    }

    /**
     * @param  array<string, mixed>  $attrs
     *
     * @throws ImageOperationException
     */
    public function storeBinary(string $binary, array $attrs): AipAsset
    {
        if ($binary === '') {
            throw new ImageOperationException(translate('The provider returned an empty image.'));
        }

        $info = @getimagesizefromstring($binary);

        if ($info === false || empty($info[0]) || empty($info[1])) {
            throw new ImageOperationException(translate('The returned file is not a readable image.'));
        }

        $mime = (string) ($info['mime'] ?? '');
        $extension = self::EXTENSIONS[$mime] ?? null;

        if ($extension === null) {
            throw new ImageOperationException(translate('Unsupported image format.'));
        }

        $ulid = (string) Str::ulid();
        $path = $this->directoryFor($attrs) . '/' . $ulid . '.' . $extension;

        if (! Storage::disk(self::DISK)->put($path, $binary)) {
            throw new ImageOperationException(translate('The image could not be saved.'));
        }

        $asset = AipAsset::create(array_merge(
            $this->assignable($attrs),
            [
                'ulid' => $ulid,
                'disk' => self::DISK,
                'path' => $path,
                'mime' => $mime,
                'width' => (int) $info[0],
                'height' => (int) $info[1],
                'bytes' => strlen($binary),
            ],
        ));

        $this->makeThumbnail($asset);

        return $asset;
    }

    /**
     * @param  array<string, mixed>  $attrs
     *
     * @throws ImageOperationException
     */
    public function storeFromUrl(string $url, array $attrs): AipAsset
    {
        try {
            $response = Http::timeout(self::DOWNLOAD_TIMEOUT)
                ->retry(2, 500, throw: false)
                ->get($url);
        } catch (\Throwable $e) {
            throw new ImageOperationException(
                translate('The generated image could not be downloaded.') . ' ' . $e->getMessage()
            );
        }

        if (! $response->successful()) {
            throw new ImageOperationException(translate(
                'The generated image could not be downloaded (HTTP :status).',
                ['status' => $response->status()],
            ));
        }

        return $this->storeBinary($response->body(), $attrs);
    }

    /**
     * @param  array<string, mixed>  $attrs
     *
     * @throws ImageOperationException
     */
    public function storeFromFile(string $absolutePath, array $attrs): AipAsset
    {
        if (! is_file($absolutePath)) {
            throw new ImageOperationException(translate('The image file could not be found.'));
        }

        $binary = @file_get_contents($absolutePath);

        if ($binary === false || $binary === '') {
            throw new ImageOperationException(translate('The image file could not be read.'));
        }

        return $this->storeBinary($binary, $attrs);
    }

    /**
     * @param  array<string, mixed>  $attrs
     *
     * @throws ImageOperationException
     */
    public function storeUpload(UploadedFile $file, array $attrs): AipAsset
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw new ImageOperationException(translate('The uploaded file could not be read.'));
        }

        return $this->storeFromFile($path, $attrs);
    }

    /**
     * Called automatically by every `store*()`. A thumbnail is a nicety, never a
     * reason to lose the asset — a failure here is logged and the row keeps a null
     * `thumb_path`, which the model falls back to the full-size URL for.
     */
    public function makeThumbnail(AipAsset $asset): void
    {
        $width = (int) addon_setting(OperationRegistry::SLUG, 'thumbnail_width', 512);

        if ($width <= 0) {
            return;
        }

        // Nothing to gain from a "thumbnail" that is bigger than its source.
        if ($asset->width !== null && $asset->width > 0 && $asset->width <= $width) {
            return;
        }

        $source = null;
        $resized = null;
        $jpeg = null;

        try {
            $source = $this->localCopy($asset);

            $resized = $this->images->resize($source, ['width' => $width, 'keep_aspect' => true]);
            $jpeg = $this->images->convert($resized, ['format' => 'jpg', 'quality' => 82]);

            $thumbPath = $this->thumbPathFor($asset);
            $binary = @file_get_contents($jpeg);

            if ($binary === false) {
                return;
            }

            Storage::disk($asset->disk ?: self::DISK)->put($thumbPath, $binary);

            $asset->update(['thumb_path' => $thumbPath]);
        } catch (\Throwable $e) {
            Log::warning('[ai-image-pro] Thumbnail generation failed', [
                'asset' => $asset->ulid,
                'error' => $e->getMessage(),
            ]);
        } finally {
            if ($source !== null) {
                $this->releaseLocalCopy($asset, $source);
            }
            foreach ([$resized, $jpeg] as $temp) {
                if ($temp !== null && is_file($temp)) {
                    @unlink($temp);
                }
            }
        }
    }

    /**
     * Return an absolute LOCAL filesystem path to the asset's bytes that GD, getimagesize()
     * and file_get_contents() can read.
     *
     * On a local disk this is the real stored file (no copy). On any S3-compatible disk the
     * object has NO local path — it is streamed down to a temp file whose path is returned.
     * Callers MUST pass that path to {@see releaseLocalCopy()} in a finally block so the temp
     * copy is not leaked; releasing a local-disk path is a safe no-op (it never deletes the
     * real file).
     *
     * @throws ImageOperationException when the object cannot be read from storage.
     */
    public function localCopy(AipAsset $asset): string
    {
        $disk = $asset->disk ?: self::DISK;

        if ($this->isLocalDisk($disk)) {
            $path = Storage::disk($disk)->path($asset->path);
            if (! is_file($path)) {
                throw new ImageOperationException(translate('The image file could not be found.'));
            }

            return $path;
        }

        $stream = Storage::disk($disk)->readStream($asset->path);
        if (! is_resource($stream)) {
            throw new ImageOperationException(translate('The image could not be read from storage.'));
        }

        $extension = pathinfo($asset->path, PATHINFO_EXTENSION) ?: 'img';
        $temp = tempnam(sys_get_temp_dir(), 'aip_') ?: (sys_get_temp_dir() . '/aip_' . Str::random(16));
        $target = $temp . '.' . $extension;
        @rename($temp, $target);

        $out = @fopen($target, 'wb');
        if ($out === false) {
            fclose($stream);
            @unlink($target);
            throw new ImageOperationException(translate('A temporary working copy could not be created.'));
        }

        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);

        return $target;
    }

    /**
     * Release a path returned by {@see localCopy()}. No-op on a local disk (the path IS the
     * stored file); unlinks the temp copy on a cloud disk.
     */
    public function releaseLocalCopy(AipAsset $asset, string $path): void
    {
        if ($this->isLocalDisk($asset->disk ?: self::DISK)) {
            return;
        }

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** Whether the asset lives on a local disk (so {@see localCopy()} returns the real file). */
    public function usesLocalDisk(AipAsset $asset): bool
    {
        return $this->isLocalDisk($asset->disk ?: self::DISK);
    }

    private function isLocalDisk(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 'local';
    }

    /** Files go for good; the row is soft-deleted so lineage (`parent_id`) survives. */
    public function delete(AipAsset $asset): void
    {
        $asset->deleteFiles();
        $asset->delete();
    }

    /**
     * Optional cross-listing in the core Documents library, off by default.
     * Best-effort: `documents` is core-owned (no ulid column, `user_id` is
     * required), so a guest asset is skipped and a schema drift is logged rather
     * than failing the operation that produced the image.
     */
    public function mirrorToDocuments(AipAsset $asset): void
    {
        if (! (bool) addon_setting(OperationRegistry::SLUG, 'mirror_to_documents', false)) {
            return;
        }

        if (! $asset->user_id) {
            return;
        }

        try {
            DB::table('documents')->insert([
                'user_id' => $asset->user_id,
                'title' => $this->documentTitle($asset),
                'content' => json_encode([
                    'ulid' => $asset->ulid,
                    'operation' => $asset->operation,
                    'path' => $asset->path,
                    'url' => $asset->url,
                    'thumb_url' => $asset->thumb_url,
                    'prompt' => $asset->prompt,
                    'model' => $asset->model,
                    'provider' => $asset->provider,
                    'width' => $asset->width,
                    'height' => $asset->height,
                ], JSON_UNESCAPED_SLASHES),
                'tool_slug' => OperationRegistry::SLUG,
                'word_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ai-image-pro] Documents mirror failed', [
                'asset' => $asset->ulid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function directoryFor(array $attrs): string
    {
        $userId = $attrs['user_id'] ?? null;

        return self::ROOT . '/' . ($userId ? (string) (int) $userId : self::GUEST_DIR);
    }

    private function thumbPathFor(AipAsset $asset): string
    {
        return dirname($asset->path) . '/' . $asset->ulid . '_thumb.jpg';
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private function assignable(array $attrs): array
    {
        return array_intersect_key($attrs, array_flip(self::ASSIGNABLE));
    }

    private function documentTitle(AipAsset $asset): string
    {
        $prompt = trim((string) $asset->prompt);

        if ($prompt !== '') {
            return Str::limit($prompt, 120);
        }

        return translate('AI Image') . ' — ' . ($asset->operation ?: (string) $asset->source);
    }
}
