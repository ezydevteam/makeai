<?php

use App\Services\AI\TokenGuard;
use App\Services\NotificationEventService;
use App\Services\Subscription\SubscriptionLifecycleService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ai:reset-usage-counters', function () {
    $updated = TokenGuard::resetDailyCounters();

    $this->info(translate(':count user AI usage counters reset.', ['count' => $updated]));
})->purpose('Reset daily AI usage counters and monthly counters on month start');

Artisan::command('notifications:subscription-reminders', function () {
    $count = app(NotificationEventService::class)->notifySubscriptionsRenewingSoon();

    $this->info(translate(':count subscription renewal reminders checked.', ['count' => $count]));
})->purpose('Send in-app subscription renewal reminders');

Artisan::command('subscriptions:expire-past-due', function () {
    $expired = app(SubscriptionLifecycleService::class)->expirePastDue();

    $this->info(translate(':count expired subscriptions processed.', ['count' => $expired]));
})->purpose('Expire past-due subscriptions and notify users');

Artisan::command('notes:prune-expired', function () {
    $deleted = \App\Models\AdminNote::whereNotNull('auto_delete_date')
        ->where('auto_delete_date', '<=', now())
        ->delete();

    $this->info(translate(':count expired notes pruned.', ['count' => $deleted]));
})->purpose('Delete admin notes past their auto-delete date');

Schedule::command('ai:reset-usage-counters')
    ->dailyAt('00:05')
    ->withoutOverlapping();

Schedule::command('notifications:subscription-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping();

Schedule::command('subscriptions:expire-past-due')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('notes:prune-expired')
    ->daily()
    ->withoutOverlapping();

Schedule::command('tools:flush-views')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('exports:cleanup')
    ->daily()
    ->withoutOverlapping();

Schedule::command('license:reverify')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('addon:reverify-licenses')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('blog:publish-scheduled')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('support:auto-close')
    ->dailyAt('06:00')
    ->withoutOverlapping();

Schedule::command('social:refresh')
    ->dailyAt('04:00')
    ->withoutOverlapping();

Schedule::command('updates:check')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('demo:reset --force')
    ->everySixHours()
    ->withoutOverlapping()
    ->when(fn () => config('demo.enabled'));

Schedule::command('rag:cleanup-ephemeral')
    ->daily()
    ->withoutOverlapping();

Schedule::command('accounts:purge-deleted')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('accounts:cleanup-expired-otps')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('analytics:aggregate')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('currency:sync-rates')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('system:cleanup-temp-files')
    ->dailyAt('05:00')
    ->withoutOverlapping();

Schedule::job(new \App\Jobs\PruneRateLimitHits())
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::call(function (): void {
    $timestamp = now()->toDateTimeString();

    Cache::put('last_scheduler_run', $timestamp, now()->addMinutes(10));
    settings_set('last_scheduler_run', $timestamp, 'string', 'system');
})->everyMinute();
