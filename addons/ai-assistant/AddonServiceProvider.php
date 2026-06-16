<?php

namespace Addons\AiAssistant;

use Addons\AiAssistant\Services\AiAssistantService;
use App\Models\AiKey;
use App\Models\AiModel;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

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

            $avatar = addon_setting('ai-assistant', 'avatar_url');
            if ($avatar && ! str_starts_with($avatar, 'http')) {
                $avatar = Storage::disk('public')->url($avatar);
            }

            return [
                'enabled' => (bool) addon_setting('ai-assistant', 'enabled', false),
                'admin_enabled' => (bool) addon_setting('ai-assistant', 'admin_enabled', true),
                'position' => addon_setting('ai-assistant', 'position', 'bottom-right'),
                'accent_color' => addon_setting('ai-assistant', 'accent_color', '#1F75FE'),
                'allow_file_upload' => (bool) addon_setting('ai-assistant', 'allow_file_upload', false),
                'allowed_file_types' => addon_setting('ai-assistant', 'allowed_file_types', 'pdf,docx,txt,csv,png,jpg'),
                'widget_icon' => addon_setting('ai-assistant', 'widget_icon', 'ti ti-robot'),
                'auto_open' => (bool) addon_setting('ai-assistant', 'auto_open', false),
                'assistant_name' => addon_setting('ai-assistant', 'assistant_name', 'AI Assistant'),
                'avatar_url' => $avatar,
                'designation' => addon_setting('ai-assistant', 'designation', 'Your AI Helper'),
                'greeting_message' => addon_setting('ai-assistant', 'greeting_message'),
                'show_to' => addon_setting('ai-assistant', 'show_to', 'all'),
                'daily_message_limit' => (int) addon_setting('ai-assistant', 'daily_message_limit', 20),
                'default_provider' => settings('default_ai_provider', 'openai'),
                'default_model' => settings('default_ai_model', 'gpt-4o-mini'),
            ];
        });

        Inertia::share('aiProviders', function () {
            if (! is_addon_active('ai-assistant')) {
                return [];
            }

            $configuredProviders = AiKey::available()->pluck('provider')->unique();
            $providers = config('ai.providers', []);

            return AiModel::active()
                ->where('type', 'chat')
                ->whereIn('provider', $configuredProviders)
                ->select('provider')
                ->distinct()
                ->orderBy('provider')
                ->pluck('provider')
                ->map(fn (string $provider) => [
                    'value' => $provider,
                    'label' => $providers[$provider]['name'] ?? ucfirst($provider),
                ])
                ->values()
                ->toArray();
        });

        Inertia::share('aiModels', function () {
            if (! is_addon_active('ai-assistant')) {
                return [];
            }

            $configuredProviders = AiKey::available()->pluck('provider')->unique();

            return AiModel::active()
                ->where('type', 'chat')
                ->whereIn('provider', $configuredProviders)
                ->orderBy('provider')
                ->orderBy('name')
                ->get(['slug', 'name', 'provider'])
                ->map(fn (AiModel $model) => [
                    'value' => $model->slug,
                    'label' => $model->name,
                    'provider' => $model->provider,
                ])
                ->toArray();
        });
    }
}
