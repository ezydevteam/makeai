<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class AiDetectorService
{
    public function __construct(
        private readonly ?string $provider = null,
        private readonly ?string $apiKey = null,
    ) {}

    public static function fromSettings(): self
    {
        $provider = settings('external_ai_detector_provider', 'gptzero');

        if ($provider === 'sapling') {
            return new self(provider: 'sapling', apiKey: settings('external_ai_detector_sapling_api_key'));
        }

        return new self(provider: 'gptzero', apiKey: settings('external_ai_detector_gptzero_api_key'));
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
            if ($this->provider === 'gptzero') {
                $response = Http::timeout(15)
                    ->withHeader('X-Api-Key', $this->apiKey)
                    ->get('https://api.gptzero.me/v2/account');

                return ['success' => $response->successful(), 'message' => 'GPTZero API reachable.'];
            }

            $response = Http::timeout(15)
                ->withHeader('X-API-Key', $this->apiKey)
                ->get('https://api.sapling.ai/user');

            return ['success' => $response->successful(), 'message' => 'Sapling API reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
