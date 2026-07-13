<?php

declare(strict_types=1);

namespace Addons\AiImagePro;

use Addons\AiImagePro\Jobs\CleanupExpiredAssets;
use Addons\AiImagePro\Models\AipPreset;
use Addons\AiImagePro\Services\AssetService;
use Addons\AiImagePro\Services\CreditService;
use Addons\AiImagePro\Services\GenerationService;
use Addons\AiImagePro\Services\ImageAccessService;
use Addons\AiImagePro\Services\LandingContentService;
use Addons\AiImagePro\Services\LocalImageService;
use Addons\AiImagePro\Services\ModelCatalog;
use Addons\AiImagePro\Services\OperationRegistry;
use Addons\AiImagePro\Services\ProviderOperationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OperationRegistry::class);
        $this->app->singleton(ImageAccessService::class);
        $this->app->singleton(LandingContentService::class);
        $this->app->singleton(ModelCatalog::class);
        $this->app->singleton(AssetService::class);
        $this->app->singleton(CreditService::class);
        $this->app->singleton(LocalImageService::class);
        $this->app->singleton(GenerationService::class);
        $this->app->singleton(ProviderOperationService::class);
    }

    public function boot(): void
    {
        if (! is_addon_active('ai-image-pro')) {
            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\ImportImageEditorData::class,
            ]);
        }

        // Retention sweep. Only runs when the operator actually set a retention
        // window — with both windows at 0 (keep forever) the job never fires.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(new CleanupExpiredAssets)
                ->daily()
                ->when(fn () => (int) addon_setting('ai-image-pro', 'retention_days_free', 0) > 0
                    || (int) addon_setting('ai-image-pro', 'retention_days_paid', 0) > 0);
        });

        $this->shareFrontendState();
    }

    /**
     * State the theme's sidebar and the Studio need on every page. Kept lean:
     * the heavy operation catalogue is passed by the Studio controller itself,
     * not shared into every request in the app.
     */
    private function shareFrontendState(): void
    {
        Inertia::share('imagePro', function () {
            $access = app(ImageAccessService::class);

            // Resolve the WEB guard explicitly, not auth()->user(). This share runs on
            // every Inertia response, admin panel included — and `auth:admin` makes
            // `admin` the default guard, so auth()->user() hands back an Admin there.
            // The access service is typed against App\Models\User.
            $user = auth('web')->user();

            return [
                'enabled' => $access->addonEnabled(),
                'canUseStudio' => $access->canAccessStudio($user),
                'canUseLibrary' => $access->canAccessLibrary($user),
            ];
        });

        Inertia::share('imageProPresets', function () {
            if (! (bool) addon_setting('ai-image-pro', 'enabled', true)) {
                return [];
            }

            // Guard the table read: Inertia shares run on every request, including
            // ones served before the addon's migrations have been applied.
            if (! Schema::hasTable('aip_presets')) {
                return [];
            }

            return AipPreset::active()->get(['slug', 'name', 'prompt_suffix', 'negative_prompt', 'thumb_path']);
        });
    }
}
