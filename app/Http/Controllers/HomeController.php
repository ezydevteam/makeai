<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class HomeController extends Controller
{
    /**
     * Show the application landing page.
     */
    public function index()
    {
        $slug = settings('homepage_template', 'default');

        if ($slug === 'ai-chatbot' && is_addon_active('ai-chatbot') && (bool) addon_setting('ai-chatbot', 'enabled', true)) {
            return Inertia::render('Addons/ai-chatbot/Chat/Index', [
                // Chat renders full-screen (consistent with the dedicated /chat route).
                'hide_header' => true,
                'hide_footer' => true,
                'default_chat_model' => addon_setting('ai-chatbot', 'default_chat_model', 'gpt-4o-mini'),
                'allow_model_select' => (bool) addon_setting('ai-chatbot', 'allow_model_select', true),
                'show_provider_models' => (bool) addon_setting('ai-chatbot', 'show_provider_models', true),
                'show_custom_models' => (bool) addon_setting('ai-chatbot', 'show_custom_models', false),
                'custom_models' => addon_setting('ai-chatbot', 'custom_models', []),
                'allow_guest_messages' => (bool) addon_setting('ai-chatbot', 'allow_guest_messages', false),
                'available_models' => app(\App\Services\AI\ProviderRegistry::class)->availableModels(),
            ]);
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
