<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class HubSpotService
{
    private ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey;
    }

    public static function fromSettings(): self
    {
        return new self(apiKey: settings('external_hubspot_hubspot_private_app_token'));
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'No private app token configured.'];
        }

        try {
            $response = Http::timeout(15)
                ->withToken($this->apiKey)
                ->get('https://api.hubapi.com/crm/v3/objects/contacts?limit=1');

            return ['success' => $response->successful(), 'message' => 'HubSpot API reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
