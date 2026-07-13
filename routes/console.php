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
    $notifications = app(\App\Services\InAppNotificationService::class);

    $due = \App\Models\ScheduledExport::with('admin')
        ->where('is_active', true)
        ->whereNotNull('next_run_at')
        ->where('next_run_at', '<=', now())
        ->get();

    $ran = 0;
    foreach ($due as $schedule) {
        try {
            $path = $runner->generate($schedule);
            if ($path !== null && $schedule->admin) {
                $notifications->send($schedule->admin, [
                    'title' => translate('Scheduled export ready: :name', ['name' => $schedule->name]),
                    'message' => translate('Your scheduled export is ready to download.'),
                    'category' => 'export',
                    'level' => 'success',
                    'icon' => 'ti-download',
                    'action_url' => route('admin.reports.export.download', ['file' => basename($path)]),
                    'action_label' => translate('Download'),
                ]);
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
})->purpose('Generate due scheduled exports and notify admins in-app');

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

Schedule::job(new PruneRateLimitHits())
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::call(function (): void {
    $timestamp = now()->toDateTimeString();

    Cache::put('last_scheduler_run', $timestamp, now()->addMinutes(10));
    settings_set('last_scheduler_run', $timestamp, 'string', 'system');
})->everyMinute();
