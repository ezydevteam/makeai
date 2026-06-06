<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class AirtableService
{
    private ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey;
    }

    public static function fromSettings(): self
    {
        return new self(apiKey: settings('external_airtable_airtable_personal_access_token'));
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'No personal access token configured.'];
        }

        try {
            $response = Http::timeout(15)
                ->withToken($this->apiKey)
                ->get('https://api.airtable.com/v0/meta/bases');

            return ['success' => $response->successful(), 'message' => 'Airtable API reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
