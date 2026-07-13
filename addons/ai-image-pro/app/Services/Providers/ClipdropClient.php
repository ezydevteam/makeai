<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Services\Providers;

use Addons\AiImagePro\Exceptions\ImageOperationException;
use Addons\AiImagePro\Services\OperationRegistry;
use Illuminate\Support\Facades\Http;

/**
 * Clipdrop (by Stability) — a family of single-purpose edit endpoints.
 *
 * Ported from the ai-image-editor client and extended with the upscale and
 * replace-background endpoints the registry can route to it. Every method takes
 * raw image bytes and returns raw image bytes; the API key comes from this addon's
 * settings.
 */
class ClipdropClient
{
    private const BASE_URL = 'https://clipdrop-api.co';

    private const MAX_UPSCALE_DIMENSION = 4096;

    public function removeBackground(string $image): string
    {
        return $this->send(
            $this->request()
                ->attach('image_file', $image, 'input.png')
                ->post(self::BASE_URL . '/remove-background/v1'),
            'background removal',
        );
    }

    public function removeObject(string $image, string $mask): string
    {
        return $this->send(
            $this->request()
                ->attach('image_file', $image, 'input.png')
                ->attach('mask_file', $mask, 'mask.png')
                ->post(self::BASE_URL . '/cleanup/v1'),
            'object removal',
        );
    }

    /**
     * Text-guided background replacement.
     *
     * @param  array<string, mixed>  $options
     */
    public function replaceBackground(string $image, string $prompt, array $options = []): string
    {
        $fields = ['prompt' => $prompt !== '' ? $prompt : ($options['prompt'] ?? '')];

        return $this->send(
            $this->request()
                ->attach('image_file', $image, 'input.png')
                ->post(self::BASE_URL . '/replace-background/v1', $fields),
            'background replace',
        );
    }

    /**
     * Upscale to an explicit target size. When no target is supplied the input is
     * scaled by `scale` (default 2×), capped so a huge request can't be forwarded.
     *
     * @param  array<string, mixed>  $options  target_width, target_height, scale
     */
    public function upscale(string $image, array $options = []): string
    {
        [$targetWidth, $targetHeight] = $this->resolveTarget($image, $options);

        return $this->send(
            $this->request()
                ->attach('image_file', $image, 'input.png')
                ->post(self::BASE_URL . '/image-upscaling/v1/upscale', [
                    'target_width' => (string) $targetWidth,
                    'target_height' => (string) $targetHeight,
                ]),
            'upscale',
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{0: int, 1: int}
     */
    private function resolveTarget(string $image, array $options): array
    {
        $width = isset($options['target_width']) ? (int) $options['target_width'] : 0;
        $height = isset($options['target_height']) ? (int) $options['target_height'] : 0;

        if ($width <= 0 || $height <= 0) {
            $info = @getimagesizefromstring($image);
            $srcWidth = is_array($info) && ! empty($info[0]) ? (int) $info[0] : 1024;
            $srcHeight = is_array($info) && ! empty($info[1]) ? (int) $info[1] : 1024;
            $scale = max(1, (int) ($options['scale'] ?? 2));

            $width = $srcWidth * $scale;
            $height = $srcHeight * $scale;
        }

        return [
            min($width, self::MAX_UPSCALE_DIMENSION),
            min($height, self::MAX_UPSCALE_DIMENSION),
        ];
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(90)->withHeaders(['x-api-key' => $this->apiKey()]);
    }

    private function send(\Illuminate\Http\Client\Response $response, string $operation): string
    {
        if (! $response->successful()) {
            throw new ImageOperationException($this->errorMessage($response->body(), $operation));
        }

        $body = $response->body();

        if ($body === '') {
            throw new ImageOperationException(translate('The image provider returned an empty response.'));
        }

        return $body;
    }

    private function errorMessage(string $body, string $operation): string
    {
        $decoded = json_decode($body, true);
        $message = is_array($decoded) ? (string) ($decoded['error'] ?? '') : '';

        return "Clipdrop {$operation} failed" . ($message !== '' ? ": {$message}" : '.');
    }

    private function apiKey(): string
    {
        $key = (string) addon_setting(OperationRegistry::SLUG, 'clipdrop_api_key', '');

        if (trim($key) === '') {
            throw new ImageOperationException(translate('The Clipdrop API key is not configured.'));
        }

        return $key;
    }
}
