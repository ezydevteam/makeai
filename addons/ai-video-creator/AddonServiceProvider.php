<?php

namespace Addons\AiVideoCreator;

use Addons\AiVideoCreator\Services\SlideshowBuilderService;
use Addons\AiVideoCreator\Services\SubtitleService;
use Addons\AiVideoCreator\Services\TrimmerService;
use Addons\AiVideoCreator\Services\VideoProviderService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VideoProviderService::class);
        $this->app->singleton(SlideshowBuilderService::class);
        $this->app->singleton(SubtitleService::class);
        $this->app->singleton(TrimmerService::class);
    }

    public function boot(): void
    {
        if (! is_addon_active('ai-video-creator')) {
            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $deleteDays = addon_setting('ai-video-creator', 'auto_delete_days', 30);

            $schedule->job(new \Addons\AiVideoCreator\Jobs\CleanupExpiredVideos)
                ->daily()
                ->when(fn () => (int) addon_setting('ai-video-creator', 'auto_delete_days', 30) > 0);
        });

        Inertia::share('videoCreator', function () {
            return [
                'enabled'               => (bool) addon_setting('ai-video-creator', 'enabled', true),
                'show_to'               => addon_setting('ai-video-creator', 'show_to', 'logged_in'),
                'max_video_duration'    => (int) addon_setting('ai-video-creator', 'max_video_duration', 30),
                'max_storage_mb_per_user'=> (int) addon_setting('ai-video-creator', 'max_storage_mb_per_user', 500),
                'credits_text_video'    => (int) addon_setting('ai-video-creator', 'credits_text_video', 50),
                'credits_text_video_long'=> (int) addon_setting('ai-video-creator', 'credits_text_video_long', 100),
                'credits_image_video'   => (int) addon_setting('ai-video-creator', 'credits_image_video', 40),
                'credits_avatar_video'  => (int) addon_setting('ai-video-creator', 'credits_avatar_video', 80),
                'credits_slideshow'     => (int) addon_setting('ai-video-creator', 'credits_slideshow', 30),
                'credits_subtitles'     => (int) addon_setting('ai-video-creator', 'credits_subtitles', 10),
            ];
        });
    }
}
