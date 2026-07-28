<?php

namespace App\Providers;

use App\Models\BlogPost;
use App\Models\User;
use App\Observers\BlogPostObserver;
use App\Observers\UserObserver;
use App\Policies\BlogPostPolicy;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use App\Services\AddonService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->ensureEnvironmentFileExists();
        $this->registerThemeServiceProvider();

        // Every AI provider driver is built through this, so a test can bind a fake
        // driver and exercise a streaming controller without calling a real provider.
        $this->app->bind(
            \App\Services\AI\Contracts\AiDriverFactory::class,
            \App\Services\AI\Drivers\LaravelAiDriverFactory::class,
        );

        // Addons register their homepage offering into this during boot(), so it has to
        // be one shared instance for the request — a fresh one would be empty.
        $this->app->singleton(\App\Services\HomepageProviderRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register the default theme's views directory as a fallback in Laravel's view lookup paths
        if (is_dir(resource_path('themes/default/views'))) {
            \Illuminate\Support\Facades\View::addLocation(resource_path('themes/default/views'));
        }

        // Feed the root `app` layout its shell-only <head> data (fonts, theme
        // timestamp, Vite entries) — see AppHeadComposer for why this is a
        // composer and not Inertia shared props.
        \Illuminate\Support\Facades\View::composer('app', \App\View\Composers\AppHeadComposer::class);

        $this->configureHttps();
        $this->recordQueueWorkerHeartbeat();
        $this->configureInfrastructureFallbacks();
        $this->configureStripeFromAdminPanel();
        $this->configureCloudStorage();
        $this->configureBroadcasting();
        $this->syncAddonsFromFilesystem();
        $this->registerAddons();

        Gate::policy(BlogPost::class, BlogPostPolicy::class);
        BlogPost::observe(BlogPostObserver::class);
        User::observe(UserObserver::class);

        Blade::directive('ads', function ($zone) {
            return "<?php echo app(\\App\\Services\\AdsService::class)->render($zone); ?>";
        });

        if (config('app.license_test_mode') && app()->environment('production')) {
            \Illuminate\Support\Facades\Log::critical('LICENSE_TEST_MODE is enabled in a production environment! This must be disabled immediately.');
        }

        // Demo mode needs the two published account passwords to seed its accounts.
        // They have no default (see config/demo.php), so if DEMO_ENABLED=true without
        // them, DemoSeeder throws — the scheduled 6-hourly demo:reset then wipes the
        // database via migrate:refresh and fails to reseed, leaving the demo with no
        // admin/showcase login. Surface that at boot instead of only at reset time.
        if (config('demo.enabled') && (blank(config('demo.admin_password')) || blank(config('demo.user_password')))) {
            Log::warning('Demo mode is enabled (DEMO_ENABLED=true) but DEMO_ADMIN_PASSWORD and/or DEMO_USER_PASSWORD are not set in .env. DemoSeeder will fail and the scheduled demo:reset cannot recreate the demo accounts until both are set.');
        }
    }

    /**
     * Force HTTPS URL generation (and thus asset/form/redirect URLs) whenever the
     * app is served over HTTPS. Keyed off APP_URL so it works even when a reverse
     * proxy terminates TLS and PHP doesn't see it directly — buyers on shared hosts
     * behind Cloudflare/an LB otherwise get mixed-content and broken asset loads.
     * A no-op for local http development.
     */
    /**
     * Stamp a heartbeat every time a queued job finishes, so the admin panel can tell
     * whether a worker is alive rather than guessing from how much work is waiting.
     *
     * The health check used to infer liveness from `jobs` table depth, which reported a
     * green "Active" in exactly the situations that most needed a warning: that table is
     * only used by the database driver, so on Redis it is always empty and the check passed
     * unconditionally while nothing was running at all. Depth is also the wrong signal —
     * a healthy worker keeping up has an empty queue, and a momentary backlog on a
     * perfectly good one looks like an outage.
     *
     * A job completing is direct proof that a worker exists and is consuming, on any
     * driver, with no per-driver introspection. Mirrors last_scheduler_run.
     */
    private function recordQueueWorkerHeartbeat(): void
    {
        \Illuminate\Support\Facades\Queue::after(function (\Illuminate\Queue\Events\JobProcessed $event): void {
            try {
                $now = now();

                // Cache is the hot path and is what the freshness check reads first.
                \Illuminate\Support\Facades\Cache::put('last_queue_worker_run', $now->toDateTimeString(), $now->copy()->addMinutes(30));

                // The settings row is the durable fallback for when the cache is cleared
                // or is an array store. Throttled to once a minute: a busy worker would
                // otherwise write a row per job for a value with one-minute resolution.
                $stampedAt = \Illuminate\Support\Facades\Cache::get('last_queue_worker_run_persisted');

                if (! $stampedAt || $now->diffInSeconds(\Illuminate\Support\Carbon::parse($stampedAt)) >= 60) {
                    settings_set('last_queue_worker_run', $now->toDateTimeString(), 'string', 'system');
                    \Illuminate\Support\Facades\Cache::put('last_queue_worker_run_persisted', $now->toDateTimeString(), $now->copy()->addMinutes(30));
                }
            } catch (\Throwable $e) {
                // A heartbeat must never be able to fail the job that produced it.
                Log::debug('Queue worker heartbeat could not be recorded: '.$e->getMessage());
            }
        });
    }

    private function configureHttps(): void
    {
        $appUrl = strtolower((string) config('app.url'));
        $forceHttps = str_starts_with($appUrl, 'https://');

        if ($forceHttps) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }

    /**
     * Ensure the .env file exists, generate APP_KEY, and check license test mode settings.
     */
    private function ensureEnvironmentFileExists(): void
    {
        if (file_exists(base_path('.env')) || ! file_exists(base_path('.env.example'))) {
            return;
        }

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
        } catch (\Throwable) {
            // Safe fallback if random_bytes or file writing fails
            if (file_exists(base_path('.env.example'))) {
                @copy(base_path('.env.example'), base_path('.env'));
            }
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

    /**
     * When the Cloud Storage extension is enabled, rebind the `public` disk to the
     * configured S3-compatible bucket so all media reads/writes go to the cloud
     * without touching call sites. No-op when disabled/unconfigured (stays local).
     */
    private function configureCloudStorage(): void
    {
        if (! filter_var(config('app.installed', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        try {
            \App\Services\CloudStorageService::fromSettings()->apply();
        } catch (\Throwable) {
            // Database not ready (installer, migrations pending) — keep local disk.
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

        // 'polling' is our own concept, not a Laravel broadcaster: the browser
        // fetches notifications over HTTP instead. Server-side broadcast() calls
        // therefore need a no-op connection. 'null' discards them; 'log' would
        // write every payload — including notification contents — to the log file
        // for anyone who raises LOG_LEVEL to debug while troubleshooting.
        $effectiveDriver = $broadcasting->resolveDriver();
        config(['broadcasting.default' => $effectiveDriver === 'polling' ? 'null' : $effectiveDriver]);
    }

    /**
     * Dynamically register the active theme's Service Provider if it exists.
     * Registers a runtime PSR-4 class autoloader for theme classes and controllers.
     */
    private function registerThemeServiceProvider(): void
    {
        if (! filter_var(config('app.installed', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $activeTheme = null;

        try {
            $activeTheme = settings('active_theme', 'default');
            $camelTheme = ucfirst(\Illuminate\Support\Str::camel($activeTheme));
            $themeNamespace = "Resources\\Themes\\{$camelTheme}\\";
            $themePath = resource_path("themes/{$activeTheme}/");

            // Custom autoloader to dynamically require theme classes (Controllers, ServiceProvider, etc.)
            spl_autoload_register(function ($class) use ($themeNamespace, $themePath) {
                if (str_starts_with($class, $themeNamespace)) {
                    $relativeClass = substr($class, strlen($themeNamespace));
                    $filePath = $themePath . str_replace('\\', '/', $relativeClass) . '.php';
                    if (file_exists($filePath)) {
                        require_once $filePath;
                    }
                }
            });

            $providerClass = $themeNamespace . "ThemeServiceProvider";

            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        } catch (\Throwable $e) {
            // Reaching here means the ACTIVE THEME IS BROKEN, and nothing else.
            //
            // The two situations this catch was originally written for cannot get here: an
            // uninstalled app returns above on the app.installed guard, and a database that
            // is not ready is absorbed by settings() itself, which catches Exception and
            // hands back the default. What is left is a genuine fault in the theme —
            // typically a parse error in one of its classes (a ParseError raised inside the
            // autoloader's require_once) or a throw from its ThemeServiceProvider.
            //
            // Themes are bought and uploaded by buyers, so this is reachable in the wild.
            // Swallowing it silently leaves a site rendering without its theme provider and
            // absolutely nothing in the log to say why — the caller sees missing views, not
            // a broken theme. Boot must still continue: a broken theme should degrade the
            // site, not take it down.
            Log::warning("Failed to register theme '" . ($activeTheme ?? 'unknown') . "': " . $e->getMessage(), [
                'theme' => $activeTheme,
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}
