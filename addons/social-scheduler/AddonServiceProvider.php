<?php

namespace Addons\SocialScheduler;

use Addons\SocialScheduler\Services\SocialAccountService;
use Addons\SocialScheduler\Services\AiCaptionService;
use Addons\SocialScheduler\Services\BestTimeService;
use Addons\SocialScheduler\Services\RssFeedService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SocialAccountService::class);
        $this->app->singleton(AiCaptionService::class);
        $this->app->singleton(BestTimeService::class);
        $this->app->singleton(RssFeedService::class);
    }

    public function boot(): void
    {
        if (! is_addon_active('social-scheduler')) {
            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(new \Addons\SocialScheduler\Jobs\PublishSocialPost)
                ->everyFiveMinutes();

            $interval = (int) addon_setting('social-scheduler', 'rss_poll_interval_minutes', 60);
            $schedule->job(new \Addons\SocialScheduler\Jobs\PollRssFeeds)
                ->cron("*/{$interval} * * * *");

            $schedule->job(new \Addons\SocialScheduler\Jobs\FetchPostAnalytics)
                ->dailyAt('05:00')
                ->when(function () {
                    return (bool) addon_setting('social-scheduler', 'analytics_pull_enabled', true);
                });
        });

        Inertia::share('socialScheduler', function () {
            return [
                'enabled'               => (bool) addon_setting('social-scheduler', 'enabled', true),
                'approval_required'     => (bool) addon_setting('social-scheduler', 'approval_required', false),
                'max_accounts_per_user' => (int) addon_setting('social-scheduler', 'max_accounts_per_user', 10),
                'max_media_mb'          => (int) addon_setting('social-scheduler', 'max_media_mb', 50),
                'carousel_max_slides'   => (int) addon_setting('social-scheduler', 'carousel_max_slides', 10),
                'first_comment_enabled' => (bool) addon_setting('social-scheduler', 'first_comment_enabled', true),
            ];
        });
    }
}
