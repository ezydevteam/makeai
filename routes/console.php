<?php

use \App\Models\AdminNote;
use App\Services\AI\TokenGuard;
use App\Services\NotificationEventService;
use App\Services\Subscription\SubscriptionLifecycleService;
use App\Jobs\PruneRateLimitHits;
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

Artisan::command('subscriptions:apply-scheduled-changes', function () {
    $applied = app(SubscriptionLifecycleService::class)->applyScheduledChanges();

    $this->info(translate(':count scheduled plan changes applied.', ['count' => $applied]));
})->purpose('Apply scheduled plan downgrades whose effective date has passed');

Artisan::command('subscriptions:expire-past-due', function () {
    $expired = app(SubscriptionLifecycleService::class)->expirePastDue();

    $this->info(translate(':count expired subscriptions processed.', ['count' => $expired]));
})->purpose('Expire past-due subscriptions and notify users');

Artisan::command('subscriptions:refresh-credits', function () {
    $refreshed = app(SubscriptionLifecycleService::class)->refreshMonthlyCredits();

    $this->info(translate(':count subscription credit allowances refreshed.', ['count' => $refreshed]));
})->purpose('Grant the monthly credit allowance to subscriptions reaching their monthly anniversary');

Artisan::command('payments:expire-abandoned', function () {
    $expired = app(SubscriptionLifecycleService::class)->expireAbandonedCheckouts();

    $this->info(translate(':count abandoned checkout payments expired.', ['count' => $expired]));
})->purpose('Fail stale pending gateway checkouts and release their coupon slots');

Artisan::command('subscriptions:trial-reminders', function () {
    $count = app(NotificationEventService::class)->notifyTrialsEndingSoon();

    $this->info(translate(':count trial-ending reminders sent.', ['count' => $count]));
})->purpose('Remind users whose free trial ends within ~2 days');

Artisan::command('notes:prune-expired', function () {
    $deleted = AdminNote::whereNotNull('auto_delete_date')
        ->where('auto_delete_date', '<=', now())
        ->delete();

    $this->info(translate(':count expired notes pruned.', ['count' => $deleted]));
})->purpose('Delete admin notes past their auto-delete date');

Artisan::command('exports:run-scheduled', function () {
    $runner = app(\App\Services\ScheduledExportRunner::class);

    $due = \App\Models\ScheduledExport::with('admin')
        ->where('is_active', true)
        ->whereNotNull('next_run_at')
        ->where('next_run_at', '<=', now())
        ->get();

    $ran = 0;
    foreach ($due as $schedule) {
        try {
            $path = $runner->generate($schedule);

            if ($path === null) {
                // The dataset's availability gate closed since the schedule was
                // created (license downgraded, feature switched off). Without this
                // the run is a silent no-op: no file, no notice, clock still
                // advances, and the admin keeps believing the export is arriving.
                \Illuminate\Support\Facades\Log::warning('Scheduled export skipped: dataset unavailable', [
                    'id' => $schedule->id,
                    'dataset' => $schedule->dataset,
                ]);
            } elseif ($schedule->admin) {
                // Same delivery as an on-demand Export Center run: in-app notice plus
                // the hardcoded system email. The recipient is a site admin, not a
                // customer, so this deliberately does not go through a mail template.
                \App\Jobs\NotifyExportReadyJob::dispatch(
                    $schedule->admin_id,
                    $path,
                    $schedule->name,
                );
                $ran++;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Scheduled export failed', [
                'id' => $schedule->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Always advance the clock so a failing run can't jam the queue.
        $schedule->forceFill([
            'last_run_at' => now(),
            'next_run_at' => $schedule->computeNextRun(now()),
        ])->save();
    }

    $this->info(translate(':count scheduled exports processed.', ['count' => $ran]));
})->purpose('Generate due scheduled exports and notify the owning admin');

Schedule::command('ai:reset-usage-counters')
    ->dailyAt('00:05')
    ->withoutOverlapping();

Schedule::command('exports:run-scheduled')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('notifications:subscription-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping();

// Apply scheduled downgrades BEFORE expiring past-due subs so a scheduled
// change at period end is not mistaken for an expiry.
Schedule::command('subscriptions:apply-scheduled-changes')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('subscriptions:expire-past-due')
    ->hourly()
    ->withoutOverlapping();

// Hourly, not daily: the allowance is due on the subscription's own anniversary, so
// checking every hour keeps the refresh close to the time of day they subscribed.
Schedule::command('subscriptions:refresh-credits')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('payments:expire-abandoned')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('subscriptions:trial-reminders')
    ->dailyAt('09:30')
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

Schedule::command('gdpr:cleanup-exports')
    ->daily()
    ->withoutOverlapping();

Schedule::command('mail:prune-logs')
    ->daily()
    ->withoutOverlapping();

Schedule::command('license:reverify')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('addon:reverify-licenses')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('addons:check-updates')
    ->dailyAt('04:00')
    ->withoutOverlapping();

Schedule::command('theme:reverify-licenses')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('themes:check-updates')
    ->dailyAt('04:00')
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

// Runs after analytics:aggregate has had a chance to stamp aggregated_at, and
// only prunes already-aggregated rows, so daily summaries are never lost.
Schedule::command('usage:prune')
    ->dailyAt('03:30')
    ->withoutOverlapping();

Schedule::command('currency:sync-rates')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('system:cleanup-temp-files')
    ->dailyAt('05:00')
    ->withoutOverlapping();

Schedule::job(new PruneRateLimitHits())
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::call(function (): void {
    $timestamp = now()->toDateTimeString();

    Cache::put('last_scheduler_run', $timestamp, now()->addMinutes(10));
    settings_set('last_scheduler_run', $timestamp, 'string', 'system');
})->everyMinute();

// Demo-only: keep the Horizon dashboard active. The command self-gates on demo mode and a
// Redis queue, so on a real install (or without Redis) it simply no-ops. Every five minutes
// stays ahead of Horizon's recent-job trimming so the dashboard never goes quiet.
// Only where it can actually do something. Horizon watches Redis queues, so on a
// database-queue demo the command correctly refuses — but a refusal is still a non-zero
// exit, and the scheduler logs every one of those as an ERROR. Five minutes apart, that
// buried the real errors under thousands of lines a day.
Schedule::command('demo:horizon-activity')
    ->everyFiveMinutes()
    ->when(fn () => config('demo.enabled') && config('queue.default') === 'redis')
    ->withoutOverlapping();

Schedule::command('demo:reset --force')
    ->everySixHours()
    ->withoutOverlapping()
    ->when(fn () => config('demo.enabled'));
