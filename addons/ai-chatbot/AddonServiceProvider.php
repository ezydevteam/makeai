<?php

declare(strict_types=1);

namespace Addons\AiChatbot;

use Addons\AiChatbot\Jobs\CleanupGuestAttachments;
use Addons\AiChatbot\Support\ChatDefaults;
use Addons\AiChatbot\Support\ChatSeo;
use Addons\AiChatbot\Support\KnowledgeBase;
use App\Services\HomepageProviderRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind any specific chatbot services if needed in the future
    }

    /**
     * Offer the chat interface as the site homepage. The admin opts in from
     * Appearance → Theme Settings → Home; until then this only advertises itself.
     */
    private function registerHomepageProvider(): void
    {
        app(HomepageProviderRegistry::class)->register('ai-chatbot', [
            'label' => translate('AI Chatbot'),
            'description' => translate('Serve the full-screen chat interface at the site root, in place of the theme homepage.'),
            'settings_url' => fn () => route('admin.addons.settings', ['slug' => 'ai-chatbot']),
            'available' => fn () => (bool) addon_setting('ai-chatbot', 'enabled', true),
            'render' => fn () => Inertia::render('Addons/ai-chatbot/Chat/Index', [
                // Head tags come from the addon's SEO settings, shared with the /chat
                // route so both surfaces of this page describe themselves identically.
                ...ChatSeo::props(),
                // Chat renders full-screen (consistent with the dedicated /chat route).
                'hide_header' => true,
                'hide_footer' => true,
                'default_chat_model' => ChatDefaults::chatModel(),
                'allow_model_select' => (bool) addon_setting('ai-chatbot', 'allow_model_select', true),
                'show_provider_models' => (bool) addon_setting('ai-chatbot', 'show_provider_models', true),
                'show_custom_models' => (bool) addon_setting('ai-chatbot', 'show_custom_models', false),
                'custom_models' => addon_setting('ai-chatbot', 'custom_models', []),
                'allow_guest_messages' => (bool) addon_setting('ai-chatbot', 'allow_guest_messages', false),
                'available_models' => app(\App\Services\AI\ProviderRegistry::class)->availableModels(),
            ]),
        ]);
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

        $this->registerHomepageProvider();

        // Share chatbot status globally with Inertia views
        Inertia::share('chatbot', function () {
            return [
                'enabled' => (bool) addon_setting('ai-chatbot', 'enabled', true),
                'allowModelSelect' => (bool) addon_setting('ai-chatbot', 'allow_model_select', true),
                'showProviderModels' => (bool) addon_setting('ai-chatbot', 'show_provider_models', true),
                'showCustomModels' => (bool) addon_setting('ai-chatbot', 'show_custom_models', false),
                'customModels' => addon_setting('ai-chatbot', 'custom_models', []),
                'defaultChatModel' => ChatDefaults::chatModel(),
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
