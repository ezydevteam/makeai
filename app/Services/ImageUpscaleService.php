<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class ImageUpscaleService
{
    public function __construct(
        private readonly ?string $apiKey = null,
    ) {}

    public static function fromSettings(): self
    {
        return new self(apiKey: settings('external_image_upscaler_replicate_api_key'));
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
            $response = Http::timeout(15)
                ->withToken($this->apiKey)
                ->get('https://api.replicate.com/v1/account');

            return ['success' => $response->successful(), 'message' => 'Replicate API reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
