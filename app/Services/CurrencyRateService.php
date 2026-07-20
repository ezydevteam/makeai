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

            $base = $this->baseCurrencyOf($data);
            $rates = $this->normalizeRates($data, $base);

            if ($rates === []) {
                throw new \RuntimeException("{$this->provider} API returned empty rate data");
            }

            // convert_currency() treats `exchange_rate` as "units of this currency per 1
            // USD" (it divides by the source rate to reach USD). Providers do NOT all quote
            // against USD — Fixer's free tier is EUR-based — so rates must be rebased
            // before they are stored, or every conversion is off by the base pair's rate.
            $rates = $this->rebaseToUsd($rates, $base);

            $updated = 0;

            foreach ($rates as $currency => $rate) {
                $affected = \App\Models\Currency::where('code', $currency)->update([
                    'exchange_rate' => $rate,
                ]);

                if ($affected) {
                    $updated++;
                }
            }

            \Illuminate\Support\Facades\Log::info('Currency rates synced', [
                'provider' => $this->provider,
                'provider_base' => $base,
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

    /**
     * The currency a provider's rates are quoted against.
     *
     * ExchangeRate.host calls it `source`; Fixer calls it `base` and pins it to EUR on
     * the free tier. Reading only `source` (as this used to) silently defaulted Fixer to
     * "USD" and stored EUR-based rates as if they were USD-based.
     */
    private function baseCurrencyOf(array $data): string
    {
        $base = $data['source'] ?? $data['base'] ?? 'USD';

        return strtoupper((string) $base);
    }

    /**
     * Flatten a provider payload into [CODE => units of CODE per 1 unit of $base].
     *
     * ExchangeRate.host returns pair-keyed quotes ({"USDEUR": 0.92}); Fixer returns plain
     * codes ({"EUR": 1, "USD": 1.08}).
     *
     * @return array<string, float>
     */
    private function normalizeRates(array $data, string $base): array
    {
        $raw = $data['quotes'] ?? $data['rates'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        $rates = [];

        foreach ($raw as $key => $rate) {
            $code = strtoupper((string) $key);

            // "USDEUR" → "EUR"
            if (strlen($code) === strlen($base) + 3 && str_starts_with($code, $base)) {
                $code = substr($code, strlen($base));
            }

            if (strlen($code) !== 3 || ! is_numeric($rate) || (float) $rate <= 0) {
                continue;
            }

            $rates[$code] = (float) $rate;
        }

        if ($rates === []) {
            return [];
        }

        // A provider never quotes its own base against itself, but it is worth exactly 1.
        $rates[$base] = 1.0;

        return $rates;
    }

    /**
     * Rebase [CODE => per 1 $base] onto USD, so the stored rate means "per 1 USD".
     *
     * Given rates quoted per 1 BASE, 1 USD buys rate(X)/rate(USD) of currency X.
     *
     * @param  array<string, float>  $rates
     * @return array<string, float>
     */
    private function rebaseToUsd(array $rates, string $base): array
    {
        if ($base === 'USD') {
            return $rates;
        }

        $usdPerBase = $rates['USD'] ?? 0.0;

        if ($usdPerBase <= 0) {
            throw new \RuntimeException("{$this->provider} returned {$base}-based rates with no USD rate to rebase them from.");
        }

        return array_map(fn (float $rate): float => $rate / $usdPerBase, $rates);
    }
}
