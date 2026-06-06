<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class WebScraperService
{
    public function __construct(
        private readonly ?string $provider = null,
        private readonly ?string $chromePath = null,
    ) {}

    public static function fromSettings(): self
    {
        $provider = settings('external_url_scraper_provider', 'browsershot');

        return new self(
            provider: $provider,
            chromePath: settings('external_url_scraper_browsershot_chrome_path'),
        );
    }

    public function isConfigured(): bool
    {
        return $this->provider === 'goutte' || filled($this->chromePath);
    }

    public function testConnection(): array
    {
        if ($this->provider === 'goutte') {
            try {
                $response = Http::timeout(15)->get('https://httpbin.org/get');

                return ['success' => $response->successful(), 'message' => 'Goutte scraper: HTTP reachable.'];
            } catch (Throwable $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Chrome path not configured for Browsershot.'];
        }

        try {
            $response = Http::timeout(15)->get('https://httpbin.org/get');

            return ['success' => $response->successful(), 'message' => 'Browsershot: HTTP reachable. Chrome path configured.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
