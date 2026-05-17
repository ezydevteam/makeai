<?php

use App\Services\AI\TokenGuard;
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

Schedule::command('ai:reset-usage-counters')
    ->dailyAt('00:05')
    ->withoutOverlapping();

Schedule::call(function (): void {
    Cache::put('last_scheduler_run', now()->toDateTimeString(), now()->addMinutes(10));
})->everyMinute();
