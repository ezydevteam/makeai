<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class StockImageService
{
    public function __construct(
        private readonly ?string $provider = null,
        private readonly ?string $apiKey = null,
    ) {}

    public static function fromSettings(): self
    {
        $provider = settings('external_stock_image_provider', 'pixabay');

        if ($provider === 'pexels') {
            return new self(provider: 'pexels', apiKey: settings('external_stock_image_pexels_api_key'));
        }

        if ($provider === 'unsplash') {
            return new self(provider: 'unsplash', apiKey: settings('external_stock_image_unsplash_access_key'));
        }

        return new self(provider: 'pixabay', apiKey: settings('external_stock_image_pixabay_api_key'));
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'No API key configured.'];
        }

        try {
            $response = match ($this->provider) {
                'pixabay' => Http::timeout(15)->get('https://pixabay.com/api/', [
                    'key' => $this->apiKey,
                    'q' => 'test',
                    'per_page' => 1,
                ]),
                'pexels' => Http::timeout(15)
                    ->withHeader('Authorization', $this->apiKey)
                    ->get('https://api.pexels.com/v1/curated', ['per_page' => 1]),
                'unsplash' => Http::timeout(15)
                    ->withHeader('Authorization', "Client-ID {$this->apiKey}")
                    ->get('https://api.unsplash.com/photos/random', ['count' => 1]),
                default => null,
            };

            return ['success' => $response?->successful() ?? false, 'message' => "{$this->provider} API reachable."];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
