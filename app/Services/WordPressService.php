<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class WordPressService
{
    private ?string $siteUrl;
    private ?string $appPassword;

    public function __construct(?string $siteUrl = null, ?string $appPassword = null)
    {
        $this->siteUrl = $siteUrl;
        $this->appPassword = $appPassword;
    }

    public static function fromSettings(): self
    {
        return new self(
            siteUrl: settings('external_wordpress_wordpress_site_url'),
            appPassword: settings('external_wordpress_wordpress_app_password'),
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->siteUrl) && filled($this->appPassword);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => translate('Site URL and App Password are required.')];
        }

        try {
            $response = Http::timeout(15)
                ->withBasicAuth('admin', $this->appPassword)
                ->get(rtrim($this->siteUrl, '/') . '/wp-json/wp/v2/users/me');

            return ['success' => $response->successful(), 'message' => translate('WordPress REST API reachable.')];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
