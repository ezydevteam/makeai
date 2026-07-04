<?php

declare(strict_types=1);

namespace Addons\AiChatbot;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind any specific chatbot services if needed in the future
    }

    public function boot(): void
    {
        if (! is_addon_active('ai-chatbot')) {
            return;
        }

        // Load Routes
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');

        // Load Migrations
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        // Share chatbot status globally with Inertia views
        Inertia::share('chatbot', function () {
            return [
                'enabled' => (bool) addon_setting('ai-chatbot', 'enabled', true),
                'allowModelSelect' => (bool) addon_setting('ai-chatbot', 'allow_model_select', true),
                'showProviderModels' => (bool) addon_setting('ai-chatbot', 'show_provider_models', true),
                'showCustomModels' => (bool) addon_setting('ai-chatbot', 'show_custom_models', false),
                'customModels' => addon_setting('ai-chatbot', 'custom_models', []),
                'defaultChatModel' => addon_setting('ai-chatbot', 'default_chat_model', 'gpt-4o-mini'),
                'allowGuestMessages' => (bool) addon_setting('ai-chatbot', 'allow_guest_messages', false),
                'availableModels' => app(\App\Services\AI\ProviderRegistry::class)->availableModels(),
            ];
        });
    }
}
