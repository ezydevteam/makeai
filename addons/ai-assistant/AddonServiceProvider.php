<?php

namespace Addons\AiAssistant;

use Addons\AiAssistant\Services\AiAssistantService;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiAssistantService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        // Share safe config to Inertia — NEVER expose system_prompt_* or custom_api_key
        Inertia::share('aiAssistant', function () {
            if (! is_addon_active('ai-assistant')) {
                return null;
            }

            return [
                'enabled' => (bool) addon_setting('ai-assistant', 'enabled', false),
                'admin_enabled' => (bool) addon_setting('ai-assistant', 'admin_enabled', true),
                'position' => addon_setting('ai-assistant', 'position', 'bottom-right'),
                'accent_color' => addon_setting('ai-assistant', 'accent_color', '#1F75FE'),
                'assistant_name' => addon_setting('ai-assistant', 'assistant_name', 'AI Assistant'),
                'avatar_url' => addon_setting('ai-assistant', 'avatar_url'),
                'designation' => addon_setting('ai-assistant', 'designation', 'Your AI Helper'),
                'greeting_message' => addon_setting('ai-assistant', 'greeting_message'),
                'show_to' => addon_setting('ai-assistant', 'show_to', 'all'),
                'daily_message_limit' => (int) addon_setting('ai-assistant', 'daily_message_limit', 20),
            ];
        });
    }
}
