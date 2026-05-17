<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('ai_text_gen', function (Request $request) {
            $limit = (int) settings('ai_text_generation_rate_limit_per_minute', 60);

            return Limit::perMinute(max(1, $limit))->by(
                $request->user()?->id ?: $request->ip()
            );
        });
    }
}
