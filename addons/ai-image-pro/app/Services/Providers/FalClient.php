<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Services\Providers;

use Addons\AiImagePro\Exceptions\ImageOperationException;
use Addons\AiImagePro\Services\OperationRegistry;
use Illuminate\Support\Facades\Http;

/**
 * fal.ai — text-to-image generation.
 *
 * Which fal model runs is not hardcoded: it comes from the model row's
 * `meta.provider_model` (e.g. `fal-ai/flux/schnell`), passed in as
 * `options['provider_model']`. Returns raw image bytes; the caller persists them.
 */
class FalClient
{
    private const BASE_URL = 'https://fal.run';

    /**
     * @param  array<string, mixed>  $options  provider_model (required), negative_prompt,
     *                                         image_size, width, height, seed, num_images
     * @return array<int, string>  raw image bytes, one entry per output
     */
    public function generate(string $prompt, array $options = []): array
    {
        $model = trim((string) ($options['provider_model'] ?? ''));

        if ($model === '') {
            throw new ImageOperationException(translate('This fal.ai model is missing its identifier. Set meta.provider_model on the model.'));
        }

        $payload = array_filter([
            'prompt' => $prompt,
            'negative_prompt' => $options['negative_prompt'] ?? null,
            'image_size' => $this->imageSize($options),
            'seed' => isset($options['seed']) ? (int) $options['seed'] : null,
            'num_images' => max(1, (int) ($options['num_images'] ?? 1)),
        ], fn ($value) => $value !== null && $value !== '');

        $response = Http::timeout(180)
            ->withHeaders(['Authorization' => 'Key ' . $this->apiKey()])
            ->post(self::BASE_URL . '/' . ltrim($model, '/'), $payload);

        if (! $response->successful()) {
            throw new ImageOperationException($this->errorMessage($response->body()));
        }

        $images = $response->json('images');

        if (! is_array($images) || $images === []) {
            throw new ImageOperationException(translate('fal.ai finished but returned no image.'));
        }

        $binaries = [];
        foreach ($images as $image) {
            $url = is_array($image) ? ($image['url'] ?? null) : null;

            if (is_string($url) && $url !== '') {
                $binaries[] = $this->download($url);
            }
        }

        if ($binaries === []) {
            throw new ImageOperationException(translate('fal.ai finished but returned no image.'));
        }

        return $binaries;
    }

    /**
     * fal accepts either a named preset (`square_hd`, `landscape_16_9`, …) or an
     * explicit {width,height}. Prefer explicit dimensions when the caller has them.
     *
     * @param  array<string, mixed>  $options
     * @return array{width: int, height: int}|string|null
     */
    private function imageSize(array $options): array|string|null
    {
        if (isset($options['width'], $options['height'])) {
            return ['width' => (int) $options['width'], 'height' => (int) $options['height']];
        }

        $size = $options['image_size'] ?? null;

        return is_string($size) && $size !== '' ? $size : null;
    }

    private function download(string $url): string
    {
        $response = Http::timeout(120)->get($url);

        if (! $response->successful() || $response->body() === '') {
            throw new ImageOperationException(translate('Failed to download the fal.ai output image.'));
        }

        return $response->body();
    }

    private function errorMessage(string $body): string
    {
        $decoded = json_decode($body, true);
        $message = is_array($decoded)
            ? (string) (data_get($decoded, 'detail.0.msg') ?? data_get($decoded, 'detail') ?? data_get($decoded, 'message') ?? '')
            : '';

        return 'fal.ai generation failed' . ($message !== '' ? ": {$message}" : '.');
    }

    private function apiKey(): string
    {
        $key = (string) addon_setting(OperationRegistry::SLUG, 'fal_api_key', '');

        if (trim($key) === '') {
            throw new ImageOperationException(translate('The fal.ai API key is not configured.'));
        }

        return $key;
    }
}
