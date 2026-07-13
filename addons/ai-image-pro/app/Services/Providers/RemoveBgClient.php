<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Services\Providers;

use Addons\AiImagePro\Exceptions\ImageOperationException;
use Addons\AiImagePro\Services\OperationRegistry;
use Illuminate\Support\Facades\Http;

/**
 * remove.bg — single-purpose background removal.
 *
 * Ported from the ai-image-editor client: takes raw image bytes, returns raw PNG
 * bytes, and reads its key from this addon's settings. Persistence is the caller's
 * job (AssetService), never this client's.
 */
class RemoveBgClient
{
    private const BASE_URL = 'https://api.remove.bg/v1.0';

    /**
     * @param  array<string, mixed>  $options  size, bg_color
     */
    public function removeBackground(string $image, array $options = []): string
    {
        $response = Http::timeout(60)
            ->withHeaders(['X-Api-Key' => $this->apiKey()])
            ->attach('image_file', $image, 'input.png')
            ->post(self::BASE_URL . '/removebg', array_filter([
                'size' => (string) ($options['size'] ?? 'auto'),
                'format' => 'png',
                'bg_color' => (string) ($options['bg_color'] ?? ''),
            ], fn ($value) => $value !== ''));

        if (! $response->successful()) {
            throw new ImageOperationException($this->errorMessage($response->body()));
        }

        $body = $response->body();

        if ($body === '') {
            throw new ImageOperationException(translate('The image provider returned an empty response.'));
        }

        return $body;
    }

    private function errorMessage(string $body): string
    {
        $decoded = json_decode($body, true);
        $message = is_array($decoded) ? (string) data_get($decoded, 'errors.0.title', '') : '';

        return 'Remove.bg failed' . ($message !== '' ? ": {$message}" : '.');
    }

    private function apiKey(): string
    {
        $key = (string) addon_setting(OperationRegistry::SLUG, 'remove_bg_api_key', '');

        if (trim($key) === '') {
            throw new ImageOperationException(translate('The remove.bg API key is not configured.'));
        }

        return $key;
    }
}
