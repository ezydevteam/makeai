<?php

namespace Addons\AiRepurposer;

use Addons\AiRepurposer\Services\TranscriptService;
use Addons\AiRepurposer\Services\RepurposeService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TranscriptService::class);
        $this->app->singleton(RepurposeService::class);
    }

    public function boot(): void
    {
        if (! is_addon_active('ai-repurposer')) {
            return;
        }

        $this->autoloadClasses();

        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(new \Addons\AiRepurposer\Jobs\CleanupRepurposerFiles)
                ->dailyAt('04:00');
        });

        Inertia::share('repurposer', function () {
            return [
                'enabled'          => (bool) addon_setting('ai-repurposer', 'enabled', true),
                'availableFormats' => RepurposeService::FORMATS,
                'defaultFormats'   => explode(',', addon_setting('ai-repurposer', 'default_formats', 'blog_post,twitter_thread,linkedin_article,email_newsletter')),
                'maxBulkItems'     => (int) addon_setting('ai-repurposer', 'max_bulk_items', 10),
                'creditCost'       => (int) addon_setting('ai-repurposer', 'credits_per_repurpose', 15),
                'bulkCreditCost'   => (int) addon_setting('ai-repurposer', 'credits_per_bulk_item', 12),
                'maxFileMb'        => (int) addon_setting('ai-repurposer', 'max_file_size_mb', 100),
            ];
        });
    }

    private function autoloadClasses(): void
    {
        $base = __DIR__;
        $dirs = [
            'app/Models',
            'app/Services',
            'app/Jobs',
            'app/Http/Controllers',
            'app/Http/Controllers/Admin',
            'app/Http/Requests',
        ];

        foreach ($dirs as $dir) {
            $path = "{$base}/{$dir}";
            if (is_dir($path)) {
                foreach (glob("{$path}/*.php") as $file) {
                    require_once $file;
                }
            }
        }
    }
}
