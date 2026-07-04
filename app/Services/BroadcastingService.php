<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class BroadcastingService
{
    /**
     * Resolve the effective broadcasting driver at runtime.
     * Never read BROADCAST_CONNECTION env directly — always use this method.
     */
    public function resolveDriver(): string
    {
        $selected = $this->selectedDriver();

        return match ($selected) {
            'reverb' => $this->canUseReverb() ? 'reverb' : 'polling',
            'pusher' => $this->canUsePusher() ? 'pusher' : 'polling',
            default => 'polling',
        };
    }

    public function selectedDriver(): string
    {
        $fromBroadcasting = settings('broadcasting_driver');
        if (filled($fromBroadcasting) && in_array($fromBroadcasting, ['reverb', 'pusher', 'polling'], true)) {
            return $fromBroadcasting;
        }

        $fromNotifications = settings('notifications_driver');
        if (filled($fromNotifications) && in_array($fromNotifications, ['reverb', 'pusher', 'polling'], true)) {
            return $fromNotifications;
        }

        return 'reverb';
    }

    public function canUseReverb(): bool
    {
        return $this->isRedisAvailable();
    }

    public function canUsePusher(): bool
    {
        return filled($this->pusherKey()) && filled($this->pusherSecret()) && filled($this->pusherAppId());
    }

    public function isRedisAvailable(): bool
    {
        // This is reached on every page load (shared broadcasting + notification
        // Inertia props) and BroadcastingService is resolved several times per
        // request, so pinging Redis each time would make a misconfigured or
        // unreachable Redis host stall every request. Memoize for the request and
        // cache briefly across requests.
        static $available = null;

        if ($available !== null) {
            return $available;
        }

        $check = static function (): bool {
            try {
                Redis::ping();

                return true;
            } catch (\Throwable) {
                return false;
            }
        };

        try {
            return $available = (bool) Cache::remember('broadcasting:redis_available', 30, $check);
        } catch (\Throwable) {
            // Cache store itself unavailable — fall back to a single direct check.
            return $available = $check();
        }
    }

    /**
     * Returns public-safe config for the frontend.
     * Never exposes secret keys.
     *
     * @return array<string, mixed>
     */
    public function frontendConfig(): array
    {
        $driver = $this->resolveDriver();

        $config = match ($driver) {
            'reverb' => [
                'driver' => 'reverb',
                'key' => $this->reverbKey(),
                'host' => $this->reverbHost(),
                'port' => $this->reverbPort(),
                'scheme' => $this->reverbScheme(),
            ],
            'pusher' => [
                'driver' => 'pusher',
                'key' => $this->pusherKey(),
                'cluster' => $this->pusherCluster(),
            ],
            default => [
                'driver' => 'polling',
                'interval_seconds' => $this->pollingIntervalSeconds(),
            ],
        };

        $config['redis_available'] = $this->isRedisAvailable();

        return $config;
    }

    /**
     * Full config payload for admin settings page.
     * Secret fields show placeholder indicators, never actual values.
     *
     * @return array<string, mixed>
     */
    public function configPayload(): array
    {
        $selected = $this->selectedDriver();
        $effective = $this->resolveDriver();
        $degraded = $selected !== $effective;

        return [
            'driver' => $selected,
            'effective_driver' => $effective,
            'degraded' => $degraded,
            'degradation_reason' => $degraded ? $this->degradationReason($selected) : null,
            'polling_interval_seconds' => $this->pollingIntervalSeconds(),
            'reverb' => [
                'app_id' => $this->reverbAppId(),
                'app_key' => $this->reverbKey(),
                'host' => $this->reverbHost(),
                'port' => $this->reverbPort(),
                'scheme' => $this->reverbScheme(),
                'secret_configured' => filled($this->reverbSecret()),
            ],
            'pusher' => [
                'app_id' => $this->pusherAppId(),
                'key' => $this->pusherKey(),
                'cluster' => $this->pusherCluster(),
                'secret_configured' => filled($this->pusherSecret()),
            ],
            'redis_available' => $this->isRedisAvailable(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function healthStatus(): array
    {
        $selected = $this->selectedDriver();
        $effective = $this->resolveDriver();
        $degraded = $selected !== $effective;

        if ($degraded) {
            Log::warning('Broadcasting degraded', [
                'selected' => $selected,
                'effective' => $effective,
                'reason' => $this->degradationReason($selected),
            ]);
        }

        return [
            'selected' => $selected,
            'effective' => $effective,
            'degraded' => $degraded,
            'reason' => $degraded ? $this->degradationReason($selected) : null,
        ];
    }

    public function degradationReason(string $selected): string
    {
        return match ($selected) {
            'reverb' => 'Redis is not available. Reverb requires Redis for WebSocket state. Falling back to HTTP polling.',
            'pusher' => 'Pusher API keys are not fully configured. Falling back to HTTP polling.',
            default => 'Unknown configuration issue.',
        };
    }

    // ─── Credential getters — broadcasting group first, notifications group fallback, env last ───

    private function readSetting(string $broadcastingKey, string $notificationsKey, string $envKey, string $default = ''): string
    {
        $value = settings($broadcastingKey);
        if (filled($value)) {
            return (string) $value;
        }

        $value = settings($notificationsKey);
        if (filled($value)) {
            return (string) $value;
        }

        $envValue = env($envKey);
        if ($envValue !== null && $envValue !== '') {
            return (string) $envValue;
        }

        return $default;
    }

    private function readIntSetting(string $broadcastingKey, string $notificationsKey, string $envKey, int $default = 0): int
    {
        $value = settings($broadcastingKey);
        if ($value !== null && $value !== '') {
            return (int) $value;
        }

        $value = settings($notificationsKey);
        if ($value !== null && $value !== '') {
            return (int) $value;
        }

        $envValue = env($envKey);
        if ($envValue !== null && $envValue !== '') {
            return (int) $envValue;
        }

        return $default;
    }

    public function reverbAppId(): string
    {
        return $this->readSetting('broadcasting_reverb_app_id', 'notifications_reverb_app_id', 'REVERB_APP_ID');
    }

    public function reverbKey(): string
    {
        return $this->readSetting('broadcasting_reverb_app_key', 'notifications_reverb_app_key', 'REVERB_APP_KEY');
    }

    public function reverbSecret(): string
    {
        return $this->readSetting('broadcasting_reverb_app_secret', 'notifications_reverb_app_secret', 'REVERB_APP_SECRET');
    }

    public function reverbHost(): string
    {
        return $this->readSetting('broadcasting_reverb_host', 'notifications_reverb_host', 'REVERB_HOST', '127.0.0.1');
    }

    public function reverbPort(): int
    {
        return $this->readIntSetting('broadcasting_reverb_port', 'notifications_reverb_port', 'REVERB_PORT', 8080);
    }

    public function reverbScheme(): string
    {
        return $this->readSetting('broadcasting_reverb_scheme', 'notifications_reverb_scheme', 'REVERB_SCHEME', 'http');
    }

    public function pusherAppId(): string
    {
        return $this->readSetting('broadcasting_pusher_app_id', 'notifications_pusher_app_id', 'PUSHER_APP_ID');
    }

    public function pusherKey(): string
    {
        return $this->readSetting('broadcasting_pusher_key', 'notifications_pusher_key', 'PUSHER_APP_KEY');
    }

    public function pusherSecret(): string
    {
        return $this->readSetting('broadcasting_pusher_secret', 'notifications_pusher_secret', 'PUSHER_APP_SECRET');
    }

    public function pusherCluster(): string
    {
        return $this->readSetting('broadcasting_pusher_cluster', 'notifications_pusher_cluster', 'PUSHER_APP_CLUSTER', 'mt1');
    }

    public function pollingIntervalSeconds(): int
    {
        $fromBroadcasting = settings('broadcasting_polling_interval_seconds');
        if ($fromBroadcasting !== null && $fromBroadcasting !== '') {
            return max(10, min(300, (int) $fromBroadcasting));
        }

        $fromNotifications = settings('notifications_polling_interval');
        if ($fromNotifications !== null && $fromNotifications !== '') {
            return max(10, min(300, (int) $fromNotifications / 1000));
        }

        return 30;
    }
}
