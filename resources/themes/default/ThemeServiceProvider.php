<?php

// Default theme service provider — placeholder for view overrides.
// When active, this provider can register custom views, assets, and middleware.

namespace Resources\Themes\Default;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Prepend the theme's views folder to Laravel's view paths so theme templates take precedence
        if (is_dir(__DIR__.'/views')) {
            View::prependLocation(__DIR__.'/views');
        }

        // Publish assets:
        // $this->publishes([__DIR__.'/assets' => public_path('themes/default')], 'theme-default');
    }
}
