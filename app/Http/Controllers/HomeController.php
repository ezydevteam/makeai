<?php

namespace App\Http\Controllers;

use App\Services\HomepageProviderRegistry;

class HomeController extends Controller
{
    /**
     * Show the application landing page.
     */
    public function index()
    {
        // An addon may own the homepage instead of the theme (admin picks this under
        // Appearance → Theme Settings → Home). render() hands back null when the chosen
        // addon has since been deactivated or disabled, so `/` degrades to the theme's
        // own homepage rather than 404ing.
        $slug = settings('homepage_template', 'default');

        if ($slug !== 'default' && $response = app(HomepageProviderRegistry::class)->render($slug)) {
            return $response;
        }

        $activeTheme = settings('active_theme', 'default');
        $controllerClass = "Resources\\Themes\\" . ucfirst(\Illuminate\Support\Str::camel($activeTheme)) . "\\Controllers\\HomepageController";

        if (class_exists($controllerClass)) {
            return app($controllerClass)->show();
        }

        abort(404, 'Theme HomepageController not found.');
    }

    /**
     * Compile theme variables CSS stylesheet dynamically.
     */
    public function themeCss()
    {
        $activeTheme = settings('active_theme', 'default');
        $controllerClass = "Resources\\Themes\\" . ucfirst(\Illuminate\Support\Str::camel($activeTheme)) . "\\Controllers\\ThemeCssController";

        if (class_exists($controllerClass)) {
            return app($controllerClass)();
        }

        abort(404, 'Theme CSS variables not found.');
    }
}
