<?php

namespace App\Providers;

use App\Models\BlogPost;
use App\Observers\BlogPostObserver;
use App\Policies\BlogPostPolicy;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use App\Services\AddonService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! file_exists(base_path('.env')) && file_exists(base_path('.env.example'))) {
            try {
                $content = file_get_contents(base_path('.env.example'));
                $key = 'base64:' . base64_encode(random_bytes(32));
                
                // Replace APP_KEY= or APP_KEY=SomeRandomString
                if (preg_match('/^APP_KEY=/m', $content)) {
                    $content = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $content);
                } else {
                    $content .= "\nAPP_KEY=" . $key . "\n";
                }
                
                file_put_contents(base_path('.env'), $content);
                config(['app.key' => $key]);

                if (str_contains($content, 'LICENSE_TEST_MODE=true') || str_contains($content, 'LICENSE_TEST_MODE="true"')) {
                    config(['app.license_test_mode' => true]);
                }
            } catch (\Throwable $e) {
                // Safe fallback if random_bytes or file writing fails
                if (file_exists(base_path('.env.example'))) {
                    @copy(base_path('.env.example'), base_path('.env'));
                }
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureInfrastructureFallbacks();
        $this->configureStripeFromAdminPanel();
        $this->configureBroadcasting();
        $this->syncAddonsFromFilesystem();
        $this->registerAddons();

        Gate::policy(BlogPost::class, BlogPostPolicy::class);
        BlogPost::observe(BlogPostObserver::class);

        Blade::directive('ads', function ($zone) {
            return "<?php echo app(\\App\\Services\\AdsService::class)->render($zone); ?>";
        });

        if (config('app.license_test_mode') && app()->environment('production')) {
            \Illuminate\Support\Facades\Log::critical('LICENSE_TEST_MODE is enabled in a production environment! This must be disabled immediately.');
        }
    }

    /**
     * Auto-detect Redis availability and fall back cache/queue/sessions
     * to file/database when Redis is unavailable. Zero-downtime swap.
     */
    private function configureInfrastructureFallbacks(): void
    {
        // During installation, always force file cache and session drivers
        // to avoid database queries before the DB is configured and migrated.
        if (! filter_var(config('app.installed', false), FILTER_VALIDATE_BOOLEAN)) {
            config([
                'session.driver' => 'file',
                'cache.default' => 'file',
            ]);
            return;
        }

        try {
            $broadcasting = app(\App\Services\BroadcastingService::class);
            $redisAvailable = $broadcasting->isRedisAvailable();
        } catch (\Throwable) {
            $redisAvailable = false;
        }

        if ($redisAvailable) {
            return;
        }

        $cacheStore = env('CACHE_STORE', 'database');
        $queueConn = env('QUEUE_CONNECTION', 'database');
        $sessionDriver = env('SESSION_DRIVER', 'database');

        // Cache: if configured for Redis but Redis is down, swap to file
        if ($cacheStore === 'redis') {
            config(['cache.default' => 'file']);
            \Illuminate\Support\Facades\Log::warning('Infrastructure fallback: Cache driver degraded from redis to file.');
        }

        // Queue: if configured for Redis but Redis is down, swap to database
        if ($queueConn === 'redis') {
            config(['queue.default' => 'database']);
            \Illuminate\Support\Facades\Log::warning('Infrastructure fallback: Queue driver degraded from redis to database.');
        }

        // Sessions: if configured for Redis but Redis is down, swap to file
        if ($sessionDriver === 'redis') {
            config(['session.driver' => 'file']);
            \Illuminate\Support\Facades\Log::warning('Infrastructure fallback: Session driver degraded from redis to file.');
        }
    }

    /**
     * Cashier reads Stripe keys from config/.env, but buyers configure gateways in
     * the admin panel. Map the stored gateway credentials into the Cashier config
     * at boot so checkout, the billing portal, webhooks, and cancellation all work
     * without touching .env. Values set in .env keep priority.
     */
    private function configureStripeFromAdminPanel(): void
    {
        if (! filter_var(config('app.installed', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        try {
            $gateway = \App\Models\PaymentGateway::query()->where('slug', 'stripe')->first();

            if (! $gateway) {
                return;
            }

            if (! config('cashier.key') && ($key = $gateway->getCredential('publishable_key'))) {
                config(['cashier.key' => $key]);
            }

            if (! config('cashier.secret') && ($secret = $gateway->getCredential('secret_key'))) {
                config(['cashier.secret' => $secret]);
            }

            if (! config('cashier.webhook.secret') && ($webhookSecret = $gateway->getCredential('webhook_secret'))) {
                config(['cashier.webhook.secret' => $webhookSecret]);
            }
        } catch (\Throwable) {
            // Database not ready (installer, migrations pending) — keep env-based config.
        }
    }

    private function registerAddons(): void
    {
        try {
            app(AddonService::class)->registerActiveAddons();
        } catch (\Throwable) {
            // Silently skip if addons directory or settings are unavailable
        }
    }

    private function syncAddonsFromFilesystem(): void
    {
        try {
            app(AddonService::class)->syncFromFilesystem();
        } catch (\Throwable) {
            // Silently skip if addons directory doesn't exist
        }
    }

    private function configureBroadcasting(): void
    {
        try {
            $broadcasting = app(\App\Services\BroadcastingService::class);
        } catch (\Throwable) {
            return;
        }

        config([
            'broadcasting.connections.reverb.key' => $broadcasting->reverbKey(),
            'broadcasting.connections.reverb.secret' => $broadcasting->reverbSecret(),
            'broadcasting.connections.reverb.app_id' => $broadcasting->reverbAppId(),
            'broadcasting.connections.reverb.options.host' => $broadcasting->reverbHost(),
            'broadcasting.connections.reverb.options.port' => $broadcasting->reverbPort(),
            'broadcasting.connections.reverb.options.scheme' => $broadcasting->reverbScheme(),
            'broadcasting.connections.reverb.options.useTLS' => $broadcasting->reverbScheme() === 'https',
            'broadcasting.connections.pusher.key' => $broadcasting->pusherKey(),
            'broadcasting.connections.pusher.secret' => $broadcasting->pusherSecret(),
            'broadcasting.connections.pusher.app_id' => $broadcasting->pusherAppId(),
            'broadcasting.connections.pusher.options.cluster' => $broadcasting->pusherCluster(),
        ]);

        $effectiveDriver = $broadcasting->resolveDriver();
        config(['broadcasting.default' => $effectiveDriver === 'polling' ? 'log' : $effectiveDriver]);
    }
}
