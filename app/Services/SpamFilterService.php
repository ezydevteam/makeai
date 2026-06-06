<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class SpamFilterService
{
    private ?string $apiKey;
    private ?string $siteUrl;

    public function __construct(?string $apiKey = null, ?string $siteUrl = null)
    {
        $this->apiKey = $apiKey;
        $this->siteUrl = $siteUrl;
    }

    public static function fromSettings(): self
    {
        return new self(
            apiKey: settings('external_spam_filter_akismet_api_key'),
            siteUrl: settings('external_spam_filter_akismet_site_url'),
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'No API key configured.'];
        }

        try {
            $response = Http::timeout(15)
                ->asForm()
                ->post('https://rest.akismet.com/1.1/verify-key', [
                    'key' => $this->apiKey,
                    'blog' => $this->siteUrl ?: config('app.url'),
                ]);

            return ['success' => $response->body() === 'valid', 'message' => 'Akismet API reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
