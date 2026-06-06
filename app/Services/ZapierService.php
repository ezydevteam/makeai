<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class ZapierService
{
    private ?string $webhookUrl;

    public function __construct(?string $webhookUrl = null)
    {
        $this->webhookUrl = $webhookUrl;
    }

    public static function fromSettings(): self
    {
        return new self(webhookUrl: settings('external_zapier_zapier_webhook_url'));
    }

    public function isConfigured(): bool
    {
        return filled($this->webhookUrl);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'No webhook URL configured.'];
        }

        try {
            $response = Http::timeout(15)->post($this->webhookUrl, ['test' => true, 'source' => settings('app_name', translate('Application'))]);

            return ['success' => $response->successful(), 'message' => 'Webhook URL reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
