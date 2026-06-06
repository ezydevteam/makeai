<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class PushNotificationService
{
    private ?string $serverKey;

    public function __construct(?string $serverKey = null)
    {
        $this->serverKey = $serverKey;
    }

    public static function fromSettings(): self
    {
        return new self(serverKey: settings('external_push_notifications_firebase_server_key'));
    }

    public function isConfigured(): bool
    {
        return filled($this->serverKey);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'No server key configured.'];
        }

        try {
            return ['success' => true, 'message' => 'Firebase FCM key configured.'];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
