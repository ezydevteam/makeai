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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureInfrastructureFallbacks();
        $this->configureBroadcasting();
        $this->syncAddonsFromFilesystem();
        $this->registerAddons();

        Gate::policy(BlogPost::class, BlogPostPolicy::class);
        BlogPost::observe(BlogPostObserver::class);

        Blade::directive('ads', function ($zone) {
            return "<?php echo app(\\App\\Services\\AdsService::class)->render($zone); ?>";
        });
    }

    /**
     * Auto-detect Redis availability and fall back cache/queue/sessions
     * to file/database when Redis is unavailable. Zero-downtime swap.
     */
    private function configureInfrastructureFallbacks(): void
    {
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
