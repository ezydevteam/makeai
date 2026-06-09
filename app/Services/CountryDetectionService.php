<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;

class CountryDetectionService
{
    /**
     * EU/EEA country codes (ISO 3166-1 alpha-2).
     */
    private const EU_EEA_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        'IS', 'LI', 'NO', // EEA but not EU
    ];

    /**
     * Detect whether the request originates from an EU/EEA country.
     */
    public function isEuEea(Request $request): bool
    {
        $country = $this->detectCountry($request);

        if (! $country) {
            return false;
        }

        return in_array(strtoupper($country), self::EU_EEA_COUNTRIES, true);
    }

    /**
     * Attempt to detect the visitor's country code.
     * Uses Cloudflare CF-IPCountry header, then IP-based geolocation if available.
     */
    public function detectCountry(Request $request): ?string
    {
        // Cloudflare header (most CDN setups)
        $cfCountry = $request->header('CF-IPCountry');
        if ($cfCountry && strlen($cfCountry) === 2) {
            return strtoupper($cfCountry);
        }

        // Try IP-based detection via the IpGeolocationService
        try {
            $geo = app(IpGeolocationService::class);
            if ($geo->isConfigured()) {
                $ip = $request->ip();
                $response = \Illuminate\Support\Facades\Http::timeout(5)
                    ->get("https://ipinfo.io/{$ip}?token=" . $this->resolveToken());

                if ($response->successful()) {
                    $data = $response->json();

                    return $data['country'] ?? null;
                }
            }
        } catch (\Throwable) {
            // Silently fail — geolocation is best-effort
        }

        return null;
    }

    private function resolveToken(): string
    {
        return settings('external_ip_geolocation_ipinfo_token', '');
    }
}
