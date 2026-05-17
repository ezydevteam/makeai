<?php

// Default theme service provider — placeholder for view overrides.
// When active, this provider can register custom views, assets, and middleware.

namespace Resources\Themes\Default;

use Illuminate\Support\ServiceProvider;

class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Override views:
        // $this->loadViewsFrom(__DIR__.'/views', 'theme');

        // Publish assets:
        // $this->publishes([__DIR__.'/assets' => public_path('themes/default')], 'theme-default');
    }
}
