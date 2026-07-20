<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
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

        // Default to polling when nothing is configured. It needs no websocket
        // server, Redis or third-party credentials, so it is the only driver that
        // works out of the box — the right default for a fresh or shared-hosting
        // install. Reverb/pusher are opt-in once their credentials are entered.
        return 'polling';
    }

    /**
     * Reverb is usable once its credentials are present. It does NOT require
     * Redis: a single Reverb process keeps connection state in memory, and Redis
     * is only needed to share that state across several Reverb nodes. Gating on
     * Redis silently downgraded correctly-configured single-server installs to
     * polling while the admin screen showed the driver as ready.
     *
     * Host/port/scheme are excluded deliberately — they have working defaults
     * (127.0.0.1:8080 over http), matching the admin form's own readiness check.
     */
    public function canUseReverb(): bool
    {
        return $this->hasReverbCredentials() && $this->isReverbReachable();
    }

    public function hasReverbCredentials(): bool
    {
        return filled($this->reverbKey())
            && filled($this->reverbSecret())
            && filled($this->reverbAppId());
    }

    /**
     * Is a Reverb server actually accepting connections?
     *
     * Credentials alone are not enough to call Reverb usable: earlier releases
     * shipped placeholder REVERB_* values in .env.example, and those are read as
     * a fallback, so a credential-only check would mark long-standing installs
     * "ready" and hand them a WebSocket that never connects. A TCP probe tests
     * the thing that actually has to be true — that `reverb:start` is running.
     *
     * Cached and memoized exactly like isRedisAvailable(), and it replaces that
     * call on this path rather than adding to it, so the request cost is unchanged.
     */
    public function isReverbReachable(): bool
    {
        $host = $this->reverbHost();
        $port = $this->reverbPort();

        if ($host === '' || $port <= 0) {
            return false;
        }

        $check = static function () use ($host, $port): bool {
            // If the host cannot probe sockets at all, do not silently override
            // the admin's explicit choice — assume reachable and let the
            // documented "server not running" failure mode apply instead.
            if (! function_exists('fsockopen')) {
                return true;
            }

            $errno = 0;
            $errstr = '';
            $socket = @fsockopen($host, $port, $errno, $errstr, 1.0);

            if ($socket === false) {
                return false;
            }

            fclose($socket);

            return true;
        };

        // Deliberately NOT memoized in a static: queue workers are long-lived, and
        // a worker that booted while Reverb was down would otherwise keep routing
        // broadcasts to the null driver until it was restarted. The 30s cache TTL
        // bounds the probe rate while still letting a recovered server be picked up.
        try {
            return (bool) Cache::remember(
                'broadcasting:reverb_reachable:' . $host . ':' . $port,
                30,
                $check
            );
        } catch (\Throwable) {
            return $check();
        }
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
    /**
     * Pure read — deliberately does NOT log. The admin health page calls this
     * three times per render (status, detail, suggestion), so logging here wrote
     * the same warning three times on every page load. Degradation is already
     * surfaced in the UI by this return value and by configPayload().
     */
    public function healthStatus(): array
    {
        $selected = $this->selectedDriver();
        $effective = $this->resolveDriver();
        $degraded = $selected !== $effective;

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
            'reverb' => $this->hasReverbCredentials()
                ? sprintf(
                    'No Reverb server is accepting connections at %s:%d. Start it with `php artisan reverb:start` (keep it running under a process supervisor). Falling back to HTTP polling.',
                    $this->reverbHost(),
                    $this->reverbPort()
                )
                : 'Reverb credentials are incomplete — App ID, App Key and App Secret are all required. Falling back to HTTP polling.',
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
