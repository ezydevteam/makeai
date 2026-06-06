<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleDriveService
{
    private ?string $clientId;
    private ?string $clientSecret;

    public function __construct(?string $clientId = null, ?string $clientSecret = null)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public static function fromSettings(): self
    {
        return new self(
            clientId: settings('external_google_drive_google_client_id'),
            clientSecret: settings('external_google_drive_google_client_secret'),
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->clientId) && filled($this->clientSecret);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Client ID and Secret are required.'];
        }

        try {
            $response = Http::timeout(15)->get('https://www.googleapis.com/discovery/v1/apis/drive/v3/rest');

            return ['success' => $response->successful(), 'message' => 'Google Drive API reachable.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
