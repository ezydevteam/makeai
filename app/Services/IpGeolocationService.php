<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class IpGeolocationService
{
    private ?string $token;

    public function __construct(?string $token = null)
    {
        $this->token = $token;
    }

    public static function fromSettings(): self
    {
        return new self(token: settings('external_ip_geolocation_ipinfo_token'));
    }

    public function isConfigured(): bool
    {
        return filled($this->token);
    }

    /**
     * Resolve an IP address to its 2-letter country code (best-effort).
     * Returns null when unconfigured, unreachable, or on any error.
     */
    public function lookupCountry(string $ip): ?string
    {
        return $this->lookupLocation($ip)['country'];
    }

    /**
     * Resolve an IP to its country (2-letter) and city (best-effort). Returns
     * ['country' => ?string, 'city' => ?string]; both null when unconfigured,
     * unreachable, or on any error. Geolocation must never break the request.
     *
     * @return array{country: ?string, city: ?string}
     */
    public function lookupLocation(string $ip): array
    {
        $none = ['country' => null, 'city' => null];

        if (! $this->isConfigured() || $ip === '') {
            return $none;
        }

        try {
            $response = Http::timeout(5)->get("https://ipinfo.io/{$ip}", ['token' => $this->token]);

            if ($response->successful()) {
                $country = (string) $response->json('country');
                $city = trim((string) $response->json('city'));

                return [
                    'country' => strlen($country) === 2 ? strtoupper($country) : null,
                    'city' => $city !== '' ? $city : null,
                ];
            }
        } catch (Throwable) {
            // Best-effort — geolocation must never break the request.
        }

        return $none;
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'No token configured.'];
        }

        // Verify reachability + a real lookup against a known public IP.
        $country = $this->lookupCountry('8.8.8.8');

        return $country
            ? ['success' => true, 'message' => "IPInfo API reachable (8.8.8.8 → {$country})."]
            : ['success' => false, 'error' => 'IPInfo did not return a country — check the token.'];
    }
}
