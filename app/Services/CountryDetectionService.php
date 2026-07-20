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

        // IP-based detection (best-effort) via the configured IP geolocation service.
        return IpGeolocationService::fromSettings()->lookupCountry((string) $request->ip());
    }
}
