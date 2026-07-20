<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Services;

use Addons\AiImagePro\Exceptions\ImageOperationException;
use GdImage;

/**
 * The free, local half of the toolbox: resize, crop, rotate, compress, convert,
 * watermark, text, colour. GD only — this codebase ships to shared hosting where
 * Imagick is not a given, and Intervention is not a dependency.
 *
 * Two rules hold across every method here:
 *
 *  1. `guard()` runs before any `imagecreatefrom*` call. GD allocates the whole
 *     bitmap at once (~4 bytes per pixel, truecolor), and a decode that exceeds
 *     memory_limit is a fatal error, not a catchable exception — it would take
 *     the worker down with it. The only defence is to refuse the file *before*
 *     decoding, off `getimagesize()`, which reads the header alone.
 *  2. Every method returns an absolute path to a NEW temp file. Nothing here
 *     mutates its input, and nothing here persists anything — `AssetService`
 *     owns the library. Callers are expected to hand the returned path to
 *     `AssetService::storeFromFile()` and unlink it.
 */
class LocalImageService
{
    /** Truecolor GD bitmaps cost ~4 bytes/px (RGBA). */
    private const BYTES_PER_PIXEL = 4;

    /**
     * A resample holds the source bitmap and the destination bitmap at the same
     * time, and GD's internal copy needs headroom on top. Budget 2.5x the source
     * bitmap rather than 1x, or a file that "just fits" still dies mid-operation.
     */
    private const MEMORY_SAFETY_FACTOR = 2.5;

    private const TMP_DIR = 'tmp/aip';

    private const DEFAULT_QUALITY = 90;

    /** Binary-search bounds for `compress(target_kb)`. */
    private const MIN_QUALITY = 5;
    private const MAX_QUALITY = 95;
    private const SEARCH_ITERATIONS = 8;

    /** @var array<string, string> mime → canonical format token */
    private const MIME_FORMATS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/gif' => 'gif',
    ];

    /** Formats that carry an alpha channel and must not be flattened. */
    private const ALPHA_FORMATS = ['png', 'webp', 'avif', 'gif'];

    /**
     * Header-only inspection. Never decodes the bitmap, so it is safe to call on
     * a file that `guard()` would go on to reject.
     *
     * @return array{width: int, height: int, mime: string}
     *
     * @throws ImageOperationException
     */
    public function probe(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            throw new ImageOperationException(translate('The image file could not be found.'));
        }

        $info = @getimagesize($absolutePath);

        if ($info === false || empty($info[0]) || empty($info[1])) {
            throw new ImageOperationException(translate('This file is not a readable image.'));
        }

        $mime = (string) ($info['mime'] ?? '');

        if (! isset(self::MIME_FORMATS[$mime])) {
            throw new ImageOperationException(translate('Unsupported image format.'));
        }

        return [
            'width' => (int) $info[0],
            'height' => (int) $info[1],
            'mime' => $mime,
        ];
    }

    /**
     * The pre-decode gate. Must run before any `imagecreatefrom*`.
     *
     * @throws ImageOperationException
     */
    public function guard(string $absolutePath): void
    {
        if (! extension_loaded('gd')) {
            throw new ImageOperationException(
                translate('Image editing is unavailable: the PHP GD extension is not installed.')
            );
        }

        ['width' => $width, 'height' => $height] = $this->probe($absolutePath);

        $maxDimension = (int) addon_setting(OperationRegistry::SLUG, 'max_input_dimension', 8000);

        if ($maxDimension > 0 && ($width > $maxDimension || $height > $maxDimension)) {
            throw new ImageOperationException(translate(
                'This image is too large to process (:widthx:height). The maximum is :max pixels on either side.',
                ['width' => $width, 'height' => $height, 'max' => $maxDimension],
            ));
        }

        $required = (int) ($width * $height * self::BYTES_PER_PIXEL * self::MEMORY_SAFETY_FACTOR);
        $available = $this->remainingMemoryBytes();

        // null = memory_limit is -1 (unlimited); nothing to check against.
        if ($available !== null && $required > $available) {
            throw new ImageOperationException(translate(
                'This image needs more memory than the server allows. Try a smaller image.'
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $p  width?, height?, percent?, keep_aspect?
     *
     * @throws ImageOperationException
     */
    public function resize(string $src, array $p): string
    {
        $image = $this->load($src);

        try {
            $format = $this->formatOf($src);
            $srcW = imagesx($image);
            $srcH = imagesy($image);

            [$targetW, $targetH] = $this->resolveTargetSize($srcW, $srcH, $p);

            $canvas = $this->canvas($targetW, $targetH, $format);

            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);

            try {
                return $this->save($canvas, $format, $this->quality($p));
            } finally {
                imagedestroy($canvas);
            }
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * Also serves the social-media presets: a preset is nothing more than an
     * x/y/w/h the client computed, so there is no preset list to hardcode here.
     *
     * @param  array<string, mixed>  $p  x, y, width, height
     *
     * @throws ImageOperationException
     */
    public function crop(string $src, array $p): string
    {
        $image = $this->load($src);

        try {
            $format = $this->formatOf($src);
            $srcW = imagesx($image);
            $srcH = imagesy($image);

            $x = max(0, min($srcW - 1, (int) ($p['x'] ?? 0)));
            $y = max(0, min($srcH - 1, (int) ($p['y'] ?? 0)));
            $width = min((int) ($p['width'] ?? 0), $srcW - $x);
            $height = min((int) ($p['height'] ?? 0), $srcH - $y);

            if ($width <= 0 || $height <= 0) {
                throw new ImageOperationException(translate('The crop area is outside the image.'));
            }

            $canvas = $this->canvas($width, $height, $format);

            imagecopyresampled($canvas, $image, 0, 0, $x, $y, $width, $height, $width, $height);

            try {
                return $this->save($canvas, $format, $this->quality($p));
            } finally {
                imagedestroy($canvas);
            }
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * @param  array<string, mixed>  $p  degrees (0|90|180|270), flip ('none'|'h'|'v')
     *
     * @throws ImageOperationException
     */
    public function rotate(string $src, array $p): string
    {
        $degrees = (int) ($p['degrees'] ?? 0);
        $flip = (string) ($p['flip'] ?? 'none');

        if (! in_array($degrees, [0, 90, 180, 270], true)) {
            throw new ImageOperationException(translate('Rotation must be 0, 90, 180 or 270 degrees.'));
        }

        if (! in_array($flip, ['none', 'h', 'v'], true)) {
            throw new ImageOperationException(translate('Unsupported flip direction.'));
        }

        $image = $this->load($src);

        try {
            $format = $this->formatOf($src);

            if ($flip !== 'none') {
                imageflip($image, $flip === 'h' ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);
            }

            if ($degrees !== 0) {
                // Raw (unblended) alpha copy, otherwise imagerotate composites the
                // transparent background onto the pixels instead of carrying them.
                imagealphablending($image, false);
                $transparent = (int) imagecolorallocatealpha($image, 0, 0, 0, 127);

                // GD rotates counter-clockwise; users mean clockwise.
                $rotated = imagerotate($image, -$degrees, $transparent);

                if (! $rotated instanceof GdImage) {
                    throw new ImageOperationException(translate('The image could not be rotated.'));
                }

                imagedestroy($image);
                $image = $rotated;
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }

            return $this->save($image, $format, $this->quality($p));
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * Either an explicit `quality`, or a `target_kb` we binary-search the quality
     * for. PNG has no quality axis — its 0-9 zlib level is what gets searched, so
     * a PNG target is best-effort: when even the smallest encoding overshoots, the
     * smallest encoding is what you get.
     *
     * @param  array<string, mixed>  $p  quality (1-100) | target_kb
     *
     * @throws ImageOperationException
     */
    public function compress(string $src, array $p): string
    {
        $image = $this->load($src);

        try {
            $format = $this->compressibleFormat($this->formatOf($src));
            $targetKb = (int) ($p['target_kb'] ?? 0);

            if ($targetKb <= 0) {
                $quality = (int) ($p['quality'] ?? 0);

                if ($quality < 1 || $quality > 100) {
                    throw new ImageOperationException(
                        translate('Provide a quality between 1 and 100, or a target size in KB.')
                    );
                }

                return $this->save($image, $format, $quality);
            }

            $binary = $this->searchQualityForSize($image, $format, $targetKb * 1024);

            return $this->writeTemp($binary, $format);
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * @param  array<string, mixed>  $p  format ('png'|'jpg'|'webp'|'avif'), quality?, background?
     *
     * @throws ImageOperationException
     */
    public function convert(string $src, array $p): string
    {
        $format = strtolower(trim((string) ($p['format'] ?? '')));
        $format = $format === 'jpeg' ? 'jpg' : $format;

        if (! in_array($format, ['png', 'jpg', 'webp', 'avif'], true)) {
            throw new ImageOperationException(translate('Unsupported target format.'));
        }

        // AVIF encoding is a compile-time option; most shared hosts lack it.
        if ($format === 'avif' && ! function_exists('imageavif')) {
            throw new ImageOperationException(translate('AVIF is not supported by this server.'));
        }

        $image = $this->load($src);

        try {
            $width = imagesx($image);
            $height = imagesy($image);

            // Flatten onto a solid background when the target has no alpha channel,
            // otherwise transparent pixels come out black.
            $canvas = $this->canvas($width, $height, $format, (string) ($p['background'] ?? '#FFFFFF'));

            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, $width, $height);

            try {
                return $this->save($canvas, $format, $this->quality($p));
            } finally {
                imagedestroy($canvas);
            }
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * @param  array<string, mixed>  $p  text, position, opacity (0-100), size, color
     *
     * @throws ImageOperationException
     */
    public function watermark(string $src, array $p): string
    {
        $text = trim((string) ($p['text'] ?? ''));

        if ($text === '') {
            throw new ImageOperationException(translate('Watermark text is required.'));
        }

        $image = $this->load($src);

        try {
            $format = $this->formatOf($src);
            $width = imagesx($image);
            $height = imagesy($image);

            // Default to 4% of the long edge so the mark scales with the image
            // instead of vanishing on a 4K render.
            $size = (int) ($p['size'] ?? 0);
            $size = $size > 0 ? $size : max(12, (int) round(max($width, $height) * 0.04));

            $opacity = max(0, min(100, (int) ($p['opacity'] ?? 60)));
            $alpha = (int) round(127 * (1 - $opacity / 100));

            [$r, $g, $b] = $this->hexToRgb((string) ($p['color'] ?? '#FFFFFF'));

            // Drawing must blend, and the result must still save its alpha channel.
            imagealphablending($image, true);
            imagesavealpha($image, true);

            $color = (int) imagecolorallocatealpha($image, $r, $g, $b, $alpha);
            $font = $this->font();
            $margin = max(8, (int) round($size * 0.5));

            [$textW, $textH] = $this->measure($text, $size, $font);
            [$x, $y] = $this->anchor(
                (string) ($p['position'] ?? 'bottom-right'),
                $width,
                $height,
                $textW,
                $textH,
                $margin,
            );

            if ($font !== null) {
                // imagettftext's y is the baseline, not the top of the box.
                imagettftext($image, (float) $size, 0.0, $x, $y + $textH, $color, $font, $text);
            } else {
                imagestring($image, 5, $x, $y, $text, $color);
            }

            return $this->save($image, $format, $this->quality($p));
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * Composite a logo image onto the source — the image counterpart of watermark().
     * The logo is scaled to ~18% of the source's long edge (never upscaled), its
     * opacity is scaled while preserving its own per-pixel transparency, and it is
     * anchored the same way the text mark is.
     *
     * @param  array<string, mixed>  $p  position, opacity (0-100)
     *
     * @throws ImageOperationException
     */
    public function watermarkLogo(string $src, string $logoPath, array $p): string
    {
        $image = $this->load($src);
        $logo = null;
        $scaled = null;

        try {
            $format = $this->formatOf($src);
            $width = imagesx($image);
            $height = imagesy($image);

            $logo = $this->load($logoPath);
            $logoW = imagesx($logo);
            $logoH = imagesy($logo);

            // Scale to a fraction of the long edge so the mark reads on a 4K render
            // but never blows up a small source; never enlarge a small logo.
            $target = max(1, (int) round(max($width, $height) * 0.18));
            $scale = min(1.0, $target / max(1, max($logoW, $logoH)));
            $drawW = max(1, (int) round($logoW * $scale));
            $drawH = max(1, (int) round($logoH * $scale));

            $scaled = imagecreatetruecolor($drawW, $drawH);
            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
            imagefill($scaled, 0, 0, (int) imagecolorallocatealpha($scaled, 0, 0, 0, 127));
            imagecopyresampled($scaled, $logo, 0, 0, 0, 0, $drawW, $drawH, $logoW, $logoH);

            $opacity = max(0, min(100, (int) ($p['opacity'] ?? 60)));
            if ($opacity < 100) {
                $this->scaleAlpha($scaled, $opacity / 100);
            }

            $margin = max(8, (int) round(max($width, $height) * 0.03));
            [$x, $y] = $this->anchor(
                (string) ($p['position'] ?? 'bottom-right'),
                $width,
                $height,
                $drawW,
                $drawH,
                $margin,
            );

            imagealphablending($image, true);
            imagesavealpha($image, true);
            imagecopy($image, $scaled, $x, $y, 0, 0, $drawW, $drawH);

            return $this->save($image, $format, $this->quality($p));
        } finally {
            imagedestroy($image);
            if ($logo instanceof GdImage) {
                imagedestroy($logo);
            }
            if ($scaled instanceof GdImage) {
                imagedestroy($scaled);
            }
        }
    }

    /**
     * Uniformly scale a truecolor image's opacity while keeping its own per-pixel
     * alpha. GD alpha is 0 (opaque) … 127 (transparent), so a pixel's visible
     * opacity is (127-alpha)/127; multiplying that by $factor gives the new alpha.
     * Bounded to the (small) scaled logo, so the per-pixel loop is cheap.
     */
    private function scaleAlpha(GdImage $image, float $factor): void
    {
        $width = imagesx($image);
        $height = imagesy($image);

        imagealphablending($image, false);
        imagesavealpha($image, true);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $newAlpha = (int) max(0, min(127, 127 - (int) round((127 - $alpha) * $factor)));
                imagesetpixel($image, $x, $y, ($newAlpha << 24) | ($rgba & 0xFFFFFF));
            }
        }
    }

    /**
     * Ported from `ai-image-editor`'s GdEditService, with the source format kept
     * (it always re-encoded to PNG) and a bundled font so `font_size` is honoured
     * even when the caller supplies no `font_path`.
     *
     * @param  array<string, mixed>  $p  text, font_size, font_color, x, y, shadow, shadow_color, font_path
     *
     * @throws ImageOperationException
     */
    public function textOverlay(string $src, array $p): string
    {
        $text = trim((string) ($p['text'] ?? ''));

        if ($text === '') {
            throw new ImageOperationException(translate('Text is required.'));
        }

        $image = $this->load($src);

        try {
            $format = $this->formatOf($src);

            $size = max(1, (int) ($p['font_size'] ?? 48));
            $x = (int) ($p['x'] ?? 0);
            $y = (int) ($p['y'] ?? 50);
            $shadow = (bool) ($p['shadow'] ?? false);

            $font = $this->font(isset($p['font_path']) ? (string) $p['font_path'] : null);

            imagealphablending($image, true);
            imagesavealpha($image, true);

            [$r, $g, $b] = $this->hexToRgb((string) ($p['font_color'] ?? '#FFFFFF'));
            $color = (int) imagecolorallocate($image, $r, $g, $b);

            if ($shadow) {
                [$sr, $sg, $sb] = $this->hexToRgb((string) ($p['shadow_color'] ?? '#000000'));
                $shadowColor = (int) imagecolorallocatealpha($image, $sr, $sg, $sb, 40);

                $this->drawText($image, $text, $x + 2, $y + 2, $size, $shadowColor, $font);
            }

            $this->drawText($image, $text, $x, $y, $size, $color, $font);

            return $this->save($image, $format, $this->quality($p));
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * Ported from `ai-image-editor`'s GdEditService. Hue and saturation need a
     * per-pixel HSL round-trip — that is O(w*h) in PHP, which is exactly why
     * `guard()`'s `max_input_dimension` matters before this runs.
     *
     * @param  array<string, mixed>  $p  brightness, contrast, saturation, hue, sharpness (each -100..100)
     *
     * @throws ImageOperationException
     */
    public function colorCorrection(string $src, array $p): string
    {
        $image = $this->load($src);

        try {
            $format = $this->formatOf($src);

            $brightness = (float) ($p['brightness'] ?? 0);
            $contrast = (float) ($p['contrast'] ?? 0);
            $saturation = (float) ($p['saturation'] ?? 0);
            $hue = (float) ($p['hue'] ?? 0);
            $sharpness = (float) ($p['sharpness'] ?? 0);

            if ($brightness !== 0.0) {
                // -100..100 → GD's -255..255
                imagefilter($image, IMG_FILTER_BRIGHTNESS, (int) round($brightness * 2.55));
            }

            if ($contrast !== 0.0) {
                // GD's contrast filter is inverted: negative = more contrast.
                imagefilter($image, IMG_FILTER_CONTRAST, -(int) round($contrast));
            }

            if ($saturation !== 0.0 || $hue !== 0.0) {
                $this->applyHsl($image, $saturation, $hue);
            }

            if ($sharpness > 0) {
                $strength = $sharpness / 100;
                imageconvolution($image, [
                    [0.0, -$strength, 0.0],
                    [-$strength, 1 + 4 * $strength, -$strength],
                    [0.0, -$strength, 0.0],
                ], 1.0, 0.0);
            }

            return $this->save($image, $format, $this->quality($p));
        } finally {
            imagedestroy($image);
        }
    }

    // ── Decoding ────────────────────────────────────────────────────────────

    /**
     * @throws ImageOperationException
     */
    private function load(string $absolutePath): GdImage
    {
        $this->guard($absolutePath);

        $mime = $this->probe($absolutePath)['mime'];

        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($absolutePath),
            'image/png' => @imagecreatefrompng($absolutePath),
            'image/webp' => @imagecreatefromwebp($absolutePath),
            'image/gif' => @imagecreatefromgif($absolutePath),
            'image/avif' => function_exists('imagecreatefromavif')
                ? @imagecreatefromavif($absolutePath)
                : false,
            default => false,
        };

        if (! $image instanceof GdImage) {
            throw new ImageOperationException(translate('This image could not be opened.'));
        }

        // A palette source (GIF, indexed PNG) has no alpha channel to work with and
        // breaks resampling; normalise everything to truecolor up front.
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagesavealpha($image, true);

        return $image;
    }

    /** Canonical format token of the source file, mapped to what we can re-encode. */
    private function formatOf(string $absolutePath): string
    {
        $format = self::MIME_FORMATS[$this->probe($absolutePath)['mime']] ?? 'png';

        // GIF is read but never written back (animation would be lost anyway), and
        // AVIF can only round-trip when this build can encode it.
        if ($format === 'gif' || ($format === 'avif' && ! function_exists('imageavif'))) {
            return 'png';
        }

        return $format;
    }

    // ── Encoding ────────────────────────────────────────────────────────────

    /**
     * @throws ImageOperationException
     */
    private function save(GdImage $image, string $format, ?int $quality = null): string
    {
        $binary = $this->encode($image, $format, $quality);

        return $this->writeTemp($binary, $format);
    }

    /**
     * Encode to memory. Used by the `target_kb` search, which would otherwise
     * write (and delete) a file per probe.
     *
     * @throws ImageOperationException
     */
    private function encode(GdImage $image, string $format, ?int $quality = null): string
    {
        $quality ??= self::DEFAULT_QUALITY;
        $quality = max(1, min(100, $quality));

        if (! $this->supportsAlpha($format)) {
            imagealphablending($image, true);
            imagesavealpha($image, false);
        } else {
            imagesavealpha($image, true);
        }

        ob_start();

        $ok = match ($format) {
            'jpg' => imagejpeg($image, null, $quality),
            'png' => imagepng($image, null, $this->pngLevel($quality)),
            'webp' => imagewebp($image, null, $quality),
            'avif' => function_exists('imageavif') && imageavif($image, null, $quality),
            default => false,
        };

        $binary = (string) ob_get_clean();

        if (! $ok || $binary === '') {
            throw new ImageOperationException(translate('The image could not be encoded.'));
        }

        return $binary;
    }

    /**
     * Highest quality whose encoding still fits the byte budget. When nothing
     * fits, the smallest encoding we found is returned rather than failing — a
     * slightly-over-target file beats no file.
     *
     * @throws ImageOperationException
     */
    private function searchQualityForSize(GdImage $image, string $format, int $targetBytes): string
    {
        $low = self::MIN_QUALITY;
        $high = self::MAX_QUALITY;

        $best = null;
        $smallest = $this->encode($image, $format, $low);

        if (strlen($smallest) <= $targetBytes) {
            $best = $smallest;
        }

        for ($i = 0; $i < self::SEARCH_ITERATIONS && $low <= $high; $i++) {
            $mid = intdiv($low + $high, 2);
            $candidate = $this->encode($image, $format, $mid);

            if (strlen($candidate) <= $targetBytes) {
                $best = $candidate;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $best ?? $smallest;
    }

    /**
     * @throws ImageOperationException
     */
    private function writeTemp(string $binary, string $format): string
    {
        $path = $this->tempPath($format);

        if (file_put_contents($path, $binary) === false) {
            throw new ImageOperationException(translate('The processed image could not be written to disk.'));
        }

        return $path;
    }

    /**
     * @throws ImageOperationException
     */
    private function tempPath(string $format): string
    {
        $dir = storage_path('app/' . self::TMP_DIR);

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new ImageOperationException(translate('The temporary image directory could not be created.'));
        }

        return $dir . DIRECTORY_SEPARATOR . uniqid('aip_', true) . '.' . $format;
    }

    // ── Geometry & canvas ───────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $p
     * @return array{0: int, 1: int}
     *
     * @throws ImageOperationException
     */
    private function resolveTargetSize(int $srcW, int $srcH, array $p): array
    {
        $percent = (float) ($p['percent'] ?? 0);

        if ($percent > 0) {
            $targetW = (int) round($srcW * $percent / 100);
            $targetH = (int) round($srcH * $percent / 100);
        } else {
            $width = (int) ($p['width'] ?? 0);
            $height = (int) ($p['height'] ?? 0);

            if ($width <= 0 && $height <= 0) {
                throw new ImageOperationException(translate('Provide a width, a height or a percentage.'));
            }

            $keepAspect = (bool) ($p['keep_aspect'] ?? true);

            if (! $keepAspect) {
                $targetW = $width > 0 ? $width : $srcW;
                $targetH = $height > 0 ? $height : $srcH;
            } elseif ($width > 0 && $height > 0) {
                // Fit inside the box.
                $ratio = min($width / $srcW, $height / $srcH);
                $targetW = (int) round($srcW * $ratio);
                $targetH = (int) round($srcH * $ratio);
            } elseif ($width > 0) {
                $targetW = $width;
                $targetH = (int) round($srcH * ($width / $srcW));
            } else {
                $targetH = $height;
                $targetW = (int) round($srcW * ($height / $srcH));
            }
        }

        return $this->capToMaxOutput(max(1, $targetW), max(1, $targetH));
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function capToMaxOutput(int $width, int $height): array
    {
        $max = (int) addon_setting(OperationRegistry::SLUG, 'max_output_dimension', 4096);

        if ($max <= 0 || ($width <= $max && $height <= $max)) {
            return [$width, $height];
        }

        $ratio = min($max / $width, $max / $height);

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }

    /**
     * A destination canvas prepared for the target format: transparent when the
     * format keeps alpha, filled with `$background` when it does not.
     *
     * @throws ImageOperationException
     */
    private function canvas(int $width, int $height, string $format, string $background = '#FFFFFF'): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);

        if (! $canvas instanceof GdImage) {
            throw new ImageOperationException(translate('The image could not be processed.'));
        }

        if ($this->supportsAlpha($format)) {
            // Blending OFF is what makes imagecopyresampled carry the source alpha
            // through instead of compositing it against the canvas.
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, (int) imagecolorallocatealpha($canvas, 0, 0, 0, 127));

            return $canvas;
        }

        [$r, $g, $b] = $this->hexToRgb($background);
        imagealphablending($canvas, true);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, $r, $g, $b));

        return $canvas;
    }

    private function supportsAlpha(string $format): bool
    {
        return in_array($format, self::ALPHA_FORMATS, true);
    }

    private function compressibleFormat(string $format): string
    {
        return in_array($format, ['jpg', 'png', 'webp', 'avif'], true) ? $format : 'jpg';
    }

    /** imagepng() takes a 0-9 zlib level, not a quality. */
    private function pngLevel(int $quality): int
    {
        return max(0, min(9, (int) round((100 - $quality) / 100 * 9)));
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function quality(array $p): ?int
    {
        $quality = (int) ($p['quality'] ?? 0);

        return $quality >= 1 && $quality <= 100 ? $quality : null;
    }

    // ── Text ────────────────────────────────────────────────────────────────

    /**
     * A TrueType font is what makes `size` mean anything — GD's built-in bitmap
     * fonts are fixed at 5 sizes. mpdf is a hard composer dependency, so its
     * DejaVuSans is always on disk; if a build ever prunes it we fall back to the
     * bitmap font rather than failing the operation.
     */
    private function font(?string $preferred = null): ?string
    {
        if ($preferred !== null && $preferred !== '' && is_file($preferred)) {
            return $preferred;
        }

        if (! function_exists('imagettftext')) {
            return null;
        }

        $bundled = base_path('vendor/mpdf/mpdf/ttfonts/DejaVuSans.ttf');

        return is_file($bundled) ? $bundled : null;
    }

    private function drawText(GdImage $image, string $text, int $x, int $y, int $size, int $color, ?string $font): void
    {
        if ($font !== null) {
            imagettftext($image, (float) $size, 0.0, $x, $y, $color, $font, $text);

            return;
        }

        imagestring($image, 5, $x, $y, $text, $color);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function measure(string $text, int $size, ?string $font): array
    {
        if ($font === null) {
            return [
                imagefontwidth(5) * strlen($text),
                imagefontheight(5),
            ];
        }

        $box = imagettfbbox((float) $size, 0.0, $font, $text);

        if ($box === false) {
            return [$size * strlen($text), $size];
        }

        return [
            (int) abs($box[2] - $box[0]),
            (int) abs($box[7] - $box[1]),
        ];
    }

    /**
     * @return array{0: int, 1: int} top-left corner of the text box
     */
    private function anchor(
        string $position,
        int $imageW,
        int $imageH,
        int $textW,
        int $textH,
        int $margin,
    ): array {
        return match ($position) {
            'top-left' => [$margin, $margin],
            'top-right' => [$imageW - $textW - $margin, $margin],
            'bottom-left' => [$margin, $imageH - $textH - $margin],
            'center' => [
                (int) round(($imageW - $textW) / 2),
                (int) round(($imageH - $textH) / 2),
            ],
            default => [$imageW - $textW - $margin, $imageH - $textH - $margin],
        };
    }

    // ── Colour ──────────────────────────────────────────────────────────────

    /**
     * Per-pixel HSL round-trip, preserving the alpha channel (the ai-image-editor
     * original dropped it, which turned every transparent PNG opaque).
     */
    private function applyHsl(GdImage $image, float $saturationDelta, float $hueDelta): void
    {
        $width = imagesx($image);
        $height = imagesy($image);

        imagealphablending($image, false);
        imagesavealpha($image, true);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($image, $x, $y);

                $alpha = ($rgba >> 24) & 0x7F;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                [$h, $s, $l] = $this->rgbToHsl($r, $g, $b);

                $h = fmod($h + $hueDelta / 360, 1.0);

                if ($h < 0) {
                    $h += 1.0;
                }

                $s = max(0.0, min(1.0, $s + $saturationDelta / 100));

                [$nr, $ng, $nb] = $this->hslToRgb($h, $s, $l);

                imagesetpixel($image, $x, $y, (int) imagecolorallocatealpha($image, $nr, $ng, $nb, $alpha));
            }
        }
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private function rgbToHsl(int $r, int $g, int $b): array
    {
        $rf = $r / 255;
        $gf = $g / 255;
        $bf = $b / 255;

        $max = max($rf, $gf, $bf);
        $min = min($rf, $gf, $bf);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $rf => ($gf - $bf) / $d + ($gf < $bf ? 6 : 0),
            $gf => ($bf - $rf) / $d + 2,
            default => ($rf - $gf) / $d + 4,
        };

        return [$h / 6, $s, $l];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hslToRgb(float $h, float $s, float $l): array
    {
        if ($s === 0.0) {
            $v = (int) round($l * 255);

            return [$v, $v, $v];
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        return [
            (int) round($this->hueToRgb($p, $q, $h + 1 / 3) * 255),
            (int) round($this->hueToRgb($p, $q, $h) * 255),
            (int) round($this->hueToRgb($p, $q, $h - 1 / 3) * 255),
        ];
    }

    private function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }

        if ($t > 1) {
            $t -= 1;
        }

        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }

        if ($t < 1 / 2) {
            return $q;
        }

        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return [255, 255, 255];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    // ── Memory ──────────────────────────────────────────────────────────────

    /** null when memory_limit is -1 (unlimited). */
    private function remainingMemoryBytes(): ?int
    {
        $limit = $this->memoryLimitBytes();

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - memory_get_usage(true));
    }

    private function memoryLimitBytes(): ?int
    {
        $limit = trim((string) ini_get('memory_limit'));

        if ($limit === '' || $limit === '-1') {
            return null;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
