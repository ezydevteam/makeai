<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use App\Services\AddonService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureBroadcasting();
        $this->registerAddons();

        Blade::directive('ads', function ($zone) {
            return "<?php echo app(\\App\\Services\\AdsService::class)->render($zone); ?>";
        });
    }

    private function registerAddons(): void
    {
        try {
            app(AddonService::class)->registerActiveAddons();
        } catch (\Throwable) {
            // Silently skip if addons directory or settings are unavailable
        }
    }

    private function configureBroadcasting(): void
    {
        $setting = fn (string $key, mixed $fallback): mixed => filled(settings($key)) ? settings($key) : $fallback;

        $reverb = [
            'key' => (string) $setting('notifications_reverb_app_key', env('REVERB_APP_KEY', '')),
            'secret' => (string) $setting('notifications_reverb_app_secret', env('REVERB_APP_SECRET', '')),
            'app_id' => (string) $setting('notifications_reverb_app_id', env('REVERB_APP_ID', '')),
            'host' => (string) $setting('notifications_reverb_host', env('REVERB_HOST', '127.0.0.1')),
            'port' => (int) $setting('notifications_reverb_port', env('REVERB_PORT', 8080)),
            'scheme' => (string) $setting('notifications_reverb_scheme', env('REVERB_SCHEME', 'http')),
        ];

        $pusher = [
            'key' => (string) $setting('notifications_pusher_key', env('PUSHER_APP_KEY', '')),
            'secret' => (string) $setting('notifications_pusher_secret', env('PUSHER_APP_SECRET', '')),
            'app_id' => (string) $setting('notifications_pusher_app_id', env('PUSHER_APP_ID', '')),
            'cluster' => (string) $setting('notifications_pusher_cluster', env('PUSHER_APP_CLUSTER', 'mt1')),
        ];

        config([
            'broadcasting.connections.reverb.key' => $reverb['key'],
            'broadcasting.connections.reverb.secret' => $reverb['secret'],
            'broadcasting.connections.reverb.app_id' => $reverb['app_id'],
            'broadcasting.connections.reverb.options.host' => $reverb['host'],
            'broadcasting.connections.reverb.options.port' => $reverb['port'],
            'broadcasting.connections.reverb.options.scheme' => $reverb['scheme'],
            'broadcasting.connections.reverb.options.useTLS' => $reverb['scheme'] === 'https',
            'broadcasting.connections.pusher.key' => $pusher['key'],
            'broadcasting.connections.pusher.secret' => $pusher['secret'],
            'broadcasting.connections.pusher.app_id' => $pusher['app_id'],
            'broadcasting.connections.pusher.options.cluster' => $pusher['cluster'],
        ]);

        $requestedDriver = (string) settings('notifications_driver', env('BROADCAST_CONNECTION', 'reverb'));
        $effectiveDriver = match ($requestedDriver) {
            'pusher' => filled($pusher['key']) && filled($pusher['secret']) && filled($pusher['app_id']) ? 'pusher' : 'reverb',
            'polling' => 'null',
            'log', 'null' => $requestedDriver,
            default => 'reverb',
        };

        if ($effectiveDriver === 'reverb' && (! filled($reverb['key']) || ! filled($reverb['secret']) || ! filled($reverb['app_id']))) {
            $effectiveDriver = 'log';
        }

        config(['broadcasting.default' => $effectiveDriver]);
    }
}
