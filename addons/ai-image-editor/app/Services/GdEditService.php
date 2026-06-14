<?php

declare(strict_types=1);

namespace Addons\AiImageEditor\Services;

use Addons\AiImageEditor\Services\Providers\ImageEditException;

class GdEditService
{
    public function colorCorrection(string $inputPath, array $params, string $outputPath): string
    {
        if (! extension_loaded('gd')) {
            throw new ImageEditException('PHP GD extension is not available. Color correction requires GD.');
        }

        $abs = storage_path('app/' . $inputPath);

        if (! file_exists($abs)) {
            throw new ImageEditException("Input file not found: {$inputPath}");
        }

        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));

        $img = match ($ext) {
            'jpg', 'jpeg' => imagecreatefromjpeg($abs),
            'png' => imagecreatefrompng($abs),
            'webp' => imagecreatefromwebp($abs),
            default => throw new ImageEditException("Unsupported format for color correction: {$ext}"),
        };

        if (! $img) {
            throw new ImageEditException("Failed to load image: {$abs}");
        }

        // Apply brightness: scale -100..100 to PHP's -255..255
        if (isset($params['brightness']) && $params['brightness'] !== 0) {
            imagefilter($img, IMG_FILTER_BRIGHTNESS, (int) ((float) $params['brightness'] * 2.55));
        }

        // Apply contrast: negate for PHP's inverted contrast filter
        if (isset($params['contrast']) && $params['contrast'] !== 0) {
            imagefilter($img, IMG_FILTER_CONTRAST, -(int) $params['contrast']);
        }

        // Apply saturation + hue via HSL conversion
        $satDelta = (float) ($params['saturation'] ?? 0);
        $hueDelta = (float) ($params['hue'] ?? 0);

        if ($satDelta !== 0.0 || $hueDelta !== 0.0) {
            $img = $this->applyHslAdjustments($img, $satDelta, $hueDelta);
        }

        // Apply sharpness via unsharp mask convolution
        if (! empty($params['sharpness']) && $params['sharpness'] > 0) {
            $strength = (float) $params['sharpness'] / 100;
            $sharpen = [
                [0, -1 * $strength, 0],
                [-1 * $strength, 1 + 4 * $strength, -1 * $strength],
                [0, -1 * $strength, 0],
            ];
            imageconvolution($img, $sharpen, 1, 0);
        }

        $absOutput = storage_path('app/' . $outputPath);
        $outputDir = dirname($absOutput);

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        imagepng($img, $absOutput);
        imagedestroy($img);

        return $outputPath;
    }

    public function textOverlay(string $inputPath, array $params, string $outputPath): string
    {
        if (! extension_loaded('gd')) {
            throw new ImageEditException('PHP GD extension is not available. Text overlay requires GD.');
        }

        $abs = storage_path('app/' . $inputPath);

        if (! file_exists($abs)) {
            throw new ImageEditException("Input file not found: {$inputPath}");
        }

        if (empty($params['text'])) {
            throw new ImageEditException('Text is required for text overlay.');
        }

        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));

        $img = match ($ext) {
            'jpg', 'jpeg' => imagecreatefromjpeg($abs),
            'png' => imagecreatefrompng($abs),
            'webp' => imagecreatefromwebp($abs),
            default => throw new ImageEditException("Unsupported format for text overlay: {$ext}"),
        };

        if (! $img) {
            throw new ImageEditException("Failed to load image: {$abs}");
        }

        $text = $params['text'];
        $fontSize = (int) ($params['font_size'] ?? 48);
        $fontColor = $params['font_color'] ?? '#FFFFFF';
        $x = (int) ($params['x'] ?? 0);
        $y = (int) ($params['y'] ?? 50);
        $fontPath = $params['font_path'] ?? null;
        $shadow = (bool) ($params['shadow'] ?? false);
        $shadowColorHex = $params['shadow_color'] ?? '#000000';

        // Parse hex color
        [$r, $g, $b] = $this->hexToRgb($fontColor);
        $textColor = imagecolorallocate($img, $r, $g, $b);

        if ($shadow) {
            [$sr, $sg, $sb] = $this->hexToRgb($shadowColorHex);
            $shadowColor = imagecolorallocate($img, $sr, $sg, $sb);

            if ($fontPath && file_exists($fontPath)) {
                imagettftext($img, $fontSize, 0, $x + 2, $y + 2, $shadowColor, $fontPath, $text);
            } else {
                imagestring($img, 5, $x + 2, $y + 2, $text, $shadowColor);
            }
        }

        if ($fontPath && file_exists($fontPath)) {
            imagettftext($img, $fontSize, 0, $x, $y, $textColor, $fontPath, $text);
        } else {
            imagestring($img, 5, $x, $y, $text, $textColor);
        }

        $absOutput = storage_path('app/' . $outputPath);
        $outputDir = dirname($absOutput);

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        imagepng($img, $absOutput);
        imagedestroy($img);

        return $outputPath;
    }

    private function applyHslAdjustments(\GdImage $img, float $satDelta, float $hueDelta): \GdImage
    {
        $width = imagesx($img);
        $height = imagesy($img);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                [$h, $s, $l] = $this->rgbToHsl($r, $g, $b);

                $h = fmod(($h + $hueDelta / 360), 1.0);
                if ($h < 0) {
                    $h += 1.0;
                }

                $s = max(0, min(1, $s + $satDelta / 100));

                [$nr, $ng, $nb] = $this->hslToRgb($h, $s, $l);
                $newColor = imagecolorallocate($img, (int) $nr, (int) $ng, (int) $nb);
                imagesetpixel($img, $x, $y, $newColor);
            }
        }

        return $img;
    }

    private function rgbToHsl(int $r, int $g, int $b): array
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0, 0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        switch ($max) {
            case $r:
                $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
                break;
            case $g:
                $h = ($b - $r) / $d + 2;
                break;
            case $b:
                $h = ($r - $g) / $d + 4;
                break;
        }

        $h /= 6;

        return [$h, $s, $l];
    }

    private function hslToRgb(float $h, float $s, float $l): array
    {
        if ($s === 0.0) {
            $v = (int) round($l * 255);

            return [$v, $v, $v];
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        return [
            round($this->hueToRgb($p, $q, $h + 1 / 3) * 255),
            round($this->hueToRgb($p, $q, $h) * 255),
            round($this->hueToRgb($p, $q, $h - 1 / 3) * 255),
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

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(strlen($hex) === 3 ? $hex[0] . $hex[0] : substr($hex, 0, 2)),
            hexdec(strlen($hex) === 3 ? $hex[1] . $hex[1] : substr($hex, 2, 2)),
            hexdec(strlen($hex) === 3 ? $hex[2] . $hex[2] : substr($hex, 4, 2)),
        ];
    }
}
