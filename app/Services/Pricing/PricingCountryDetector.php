<?php

namespace App\Services\Pricing;

use Illuminate\Http\Request;

class PricingCountryDetector
{
    public function detect(Request $request): string
    {
        $defaultCountry = strtoupper((string) settings('default_pricing_country', 'US'));
        $remoteAddress = (string) $request->server('REMOTE_ADDR', '');

        if ($this->isTrustedProxy($remoteAddress)) {
            $cloudflareCountry = $request->headers->get('CF-IPCountry');

            if ($this->isValidCountry($cloudflareCountry)) {
                return strtoupper($cloudflareCountry);
            }
        }

        $sessionCountry = $request->session()->get('pricing_country');

        if ($this->isValidCountry($sessionCountry)) {
            return strtoupper($sessionCountry);
        }

        return $this->isValidCountry($defaultCountry) ? $defaultCountry : 'US';
    }

    private function isTrustedProxy(string $remoteAddress): bool
    {
        $trustedProxies = collect(explode(',', (string) settings('pricing_trusted_proxy_ips', '')))
            ->map(fn (string $proxy) => trim($proxy))
            ->filter();

        if ($trustedProxies->isEmpty()) {
            return false;
        }

        return $trustedProxies->contains(fn (string $proxy) => $this->ipMatches($remoteAddress, $proxy));
    }

    private function ipMatches(string $ip, string $proxy): bool
    {
        if ($ip === $proxy) {
            return true;
        }

        if (! str_contains($proxy, '/')) {
            return false;
        }

        [$subnet, $bits] = explode('/', $proxy, 2);

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || ! filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);

        return (ip2long($ip) & $mask) === (ip2long($subnet) & $mask);
    }

    private function isValidCountry(mixed $countryCode): bool
    {
        return is_string($countryCode) && preg_match('/^[A-Z]{2}$/', strtoupper($countryCode)) === 1 && strtoupper($countryCode) !== 'XX';
    }
}
