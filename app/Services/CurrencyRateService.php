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

    public function syncRates(): array
    {
        try {
            $response = match ($this->provider) {
                'exchangerate' => Http::timeout(15)->get('https://api.exchangerate.host/live', [
                    'access_key' => $this->apiKey,
                ]),
                'fixer' => Http::timeout(15)->get('https://data.fixer.io/api/latest', [
                    'access_key' => $this->apiKey,
                ]),
                default => Http::timeout(15)->get('https://api.exchangerate.host/live', [
                    'access_key' => $this->apiKey,
                ]),
            };

            if (! $response->successful()) {
                throw new \RuntimeException("{$this->provider} API returned status {$response->status()}");
            }

            $data = $response->json();

            $rates = $data['quotes'] ?? $data['rates'] ?? [];
            if (empty($rates)) {
                throw new \RuntimeException("{$this->provider} API returned empty rate data");
            }

            $updated = 0;
            $source = $data['source'] ?? 'USD';

            foreach ($rates as $pair => $rate) {
                // Fixer returns rates like {"EUR": 0.92, "USD": 1.0}
                // ExchangeRate.host returns quotes like {"USDEUR": 0.92, "USDGBP": 0.79}
                $currency = $pair;
                if (str_starts_with($pair, $source) && strlen($pair) === strlen($source) + 3) {
                    $currency = substr($pair, 3);
                }

                $affected = \App\Models\Currency::where('code', $currency)->update([
                    'exchange_rate' => (float) $rate,
                ]);

                if ($affected) {
                    $updated++;
                }
            }

            \Illuminate\Support\Facades\Log::info('Currency rates synced', [
                'provider' => $this->provider,
                'source' => $source,
                'updated' => $updated,
            ]);

            return ['success' => true, 'updated' => $updated];
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Currency rate sync failed', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
