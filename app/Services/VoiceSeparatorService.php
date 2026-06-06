<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class VoiceSeparatorService
{
    private ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey;
    }

    public static function fromSettings(): self
    {
        return new self(apiKey: settings('external_voice_separator_lalal_api_key'));
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
            $response = Http::timeout(15)->withHeader('Authorization', "License {$this->apiKey}")->get('https://www.lalal.ai/api/');

            return ['success' => $response->successful(), 'message' => 'Lalal.ai API reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
