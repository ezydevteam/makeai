<?php

declare(strict_types=1);

namespace Addons\AiChatbot;

use Addons\AiChatbot\Jobs\CleanupGuestAttachments;
use Addons\AiChatbot\Support\KnowledgeBase;
use Illuminate\Console\Scheduling\Schedule;
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

        // Reclaim aged guest attachment uploads (nothing else owns them).
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->job(new CleanupGuestAttachments)->dailyAt('04:15');
        });

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
                'showTokenUsage' => (bool) addon_setting('ai-chatbot', 'show_token_usage', false),
                'showCreditsCharged' => (bool) addon_setting('ai-chatbot', 'show_credits_charged', true),
                'enableKnowledgeBase' => (bool) addon_setting('ai-chatbot', 'enable_knowledge_base', true),
                // Whether the KB toggle may be offered at all: the separate Knowledge Base
                // addon must be present AND installed/activated, not just autoloadable.
                // Shared globally so every chat surface (page, embedded template, shared
                // view) agrees with the server-side gate in ChatController.
                'kbAvailable' => KnowledgeBase::available(),
                'kbArticleBase' => KnowledgeBase::articleBaseUrl(),
                // Whether the KB addon exists at all, ignoring the toggle below — the admin
                // settings screen hides the "Enable Knowledge Base" row when it's false.
                'kbAddonInstalled' => KnowledgeBase::installed(),
                'enableFileUpload' => (bool) addon_setting('ai-chatbot', 'enable_file_upload', true),
                'enableVoice' => (bool) addon_setting('ai-chatbot', 'enable_voice', true),
                // Brand logo shown on the welcome screen and the active chat header
                // (NOT the sidebar). Stored as a relative path on the public disk by the
                // generic addon file-upload handler. Expose it as a ROOT-RELATIVE URL
                // (/storage/...) so the browser resolves it against the current origin —
                // Storage::url() builds an absolute URL from APP_URL/disk config, which
                // is http://localhost here and triggers mixed-content on the HTTPS site.
                'chatLogo' => media_url(addon_setting('ai-chatbot', 'chat_logo')) ?: null,
                'availableModels' => app(\App\Services\AI\ProviderRegistry::class)->availableModels(),
            ];
        });
    }
}
