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

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'No token configured.'];
        }

        try {
            $url = $this->token ? "https://ipinfo.io?token={$this->token}" : 'https://ipinfo.io/json';

            $response = Http::timeout(15)->get($url);

            return ['success' => $response->successful(), 'message' => 'IPInfo API reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
