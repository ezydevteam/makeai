<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Services\Providers;

use Addons\AiImagePro\Exceptions\ImageOperationException;
use Addons\AiImagePro\Services\OperationRegistry;
use Illuminate\Support\Facades\Http;

/**
 * Ideogram — text-to-image generation (strong at legible in-image text).
 *
 * The Ideogram model version (`V_2`, `V_2_TURBO`, …) comes from the model row's
 * `meta.provider_model`, passed in as `options['provider_model']`; nothing about
 * the model is hardcoded here. Returns raw image bytes for the caller to persist.
 */
class IdeogramClient
{
    private const BASE_URL = 'https://api.ideogram.ai';

    /**
     * @param  array<string, mixed>  $options  provider_model, negative_prompt, aspect_ratio,
     *                                         seed, num_images, magic_prompt
     * @return array<int, string>  raw image bytes, one entry per output
     */
    public function generate(string $prompt, array $options = []): array
    {
        $request = array_filter([
            'prompt' => $prompt,
            'model' => trim((string) ($options['provider_model'] ?? 'V_2')) ?: 'V_2',
            'negative_prompt' => $options['negative_prompt'] ?? null,
            'aspect_ratio' => $this->aspectRatio($options['aspect_ratio'] ?? null),
            'seed' => isset($options['seed']) ? (int) $options['seed'] : null,
            'num_images' => max(1, (int) ($options['num_images'] ?? 1)),
            'magic_prompt_option' => (string) ($options['magic_prompt'] ?? 'AUTO'),
        ], fn ($value) => $value !== null && $value !== '');

        $response = Http::timeout(180)
            ->withHeaders(['Api-Key' => $this->apiKey()])
            ->post(self::BASE_URL . '/generate', ['image_request' => $request]);

        if (! $response->successful()) {
            throw new ImageOperationException($this->errorMessage($response->body()));
        }

        $data = $response->json('data');

        if (! is_array($data) || $data === []) {
            throw new ImageOperationException(translate('Ideogram finished but returned no image.'));
        }

        $binaries = [];
        foreach ($data as $item) {
            $url = is_array($item) ? ($item['url'] ?? null) : null;

            if (is_string($url) && $url !== '') {
                $binaries[] = $this->download($url);
            }
        }

        if ($binaries === []) {
            throw new ImageOperationException(translate('Ideogram finished but returned no image.'));
        }

        return $binaries;
    }

    /**
     * Ideogram expects its aspect ratios as `ASPECT_16_9`. Translate a `16:9`
     * style key when one is given; pass through anything already in their format.
     */
    private function aspectRatio(?string $aspect): ?string
    {
        if ($aspect === null || $aspect === '') {
            return null;
        }

        if (str_starts_with($aspect, 'ASPECT_')) {
            return $aspect;
        }

        if (preg_match('/^(\d+)\s*[:x]\s*(\d+)$/', trim($aspect), $m) === 1) {
            return "ASPECT_{$m[1]}_{$m[2]}";
        }

        return null;
    }

    private function download(string $url): string
    {
        $response = Http::timeout(120)->get($url);

        if (! $response->successful() || $response->body() === '') {
            throw new ImageOperationException(translate('Failed to download the Ideogram output image.'));
        }

        return $response->body();
    }

    private function errorMessage(string $body): string
    {
        $decoded = json_decode($body, true);
        $message = is_array($decoded) ? (string) ($decoded['message'] ?? '') : '';

        return 'Ideogram generation failed' . ($message !== '' ? ": {$message}" : '.');
    }

    private function apiKey(): string
    {
        $key = (string) addon_setting(OperationRegistry::SLUG, 'ideogram_api_key', '');

        if (trim($key) === '') {
            throw new ImageOperationException(translate('The Ideogram API key is not configured.'));
        }

        return $key;
    }
}
