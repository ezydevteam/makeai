<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class GammaService
{
    private ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey;
    }

    public static function fromSettings(): self
    {
        return new self(apiKey: settings('external_gamma_gamma_api_key'));
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
            return ['success' => true, 'message' => 'Gamma key configured.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
