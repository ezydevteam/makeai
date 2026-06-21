<?php

namespace App\Providers;

use App\Services\AccessLevelService;
use Illuminate\Support\ServiceProvider;

class AccessLevelServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(AccessLevelService::class, function ($app) {
            return new AccessLevelService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
