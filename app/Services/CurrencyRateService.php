<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class CurrencyRateService
{
    private ?string $provider;
    private ?string $apiKey;

    public function __construct(?string $provider = null, ?string $apiKey = null)
    {
        $this->provider = $provider;
        $this->apiKey = $apiKey;
    }

    public static function fromSettings(): self
    {
        $provider = settings('external_currency_rates_provider', 'exchangerate');
        $apiKey = settings("external_currency_rates_{$provider}_api_key");

        return new self($provider, $apiKey);
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function testConnection(): array
    {
        try {
            $response = match ($this->provider) {
                'exchangerate' => Http::timeout(15)->get('https://api.exchangerate.host/live', [
                    'access_key' => $this->apiKey,
                    'currencies' => 'EUR,USD',
                ]),
                'fixer' => Http::timeout(15)->get('https://data.fixer.io/api/latest', [
                    'access_key' => $this->apiKey,
                    'symbols' => 'EUR,USD',
                ]),
                default => Http::timeout(15)->get('https://httpbin.org/get'),
            };

            return ['success' => $response->successful(), 'message' => "{$this->provider} API reachable."];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
