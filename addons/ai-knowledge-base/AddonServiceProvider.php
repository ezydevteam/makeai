<?php

namespace Addons\AiKnowledgeBase;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AddonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\IngestAllKbArticles::class,
            ]);
        }

        Inertia::share('kbSettings', function () {
            if (! is_addon_active('ai-knowledge-base')) {
                return null;
            }

            $logo = addon_setting('ai-knowledge-base', 'logo');

            return [
                'enabled'      => (bool) addon_setting('ai-knowledge-base', 'enabled', true),
                'public_slug'  => addon_setting('ai-knowledge-base', 'public_slug', 'help'),
                'page_title'   => addon_setting('ai-knowledge-base', 'page_title', 'Help Center'),
                'page_description' => addon_setting('ai-knowledge-base', 'page_description', ''),
                // Root-relative URL so it resolves against the current origin (avoids
                // http/https mismatch from an absolute Storage::url()). See ai-chatbot.
                'logo' => media_url($logo) ?: null,
                'header_menu' => addon_setting('ai-knowledge-base', 'header_menu', 'main'),
                'footer_menu' => addon_setting('ai-knowledge-base', 'footer_menu', 'footer'),
                'show_vote_buttons' => (bool) addon_setting('ai-knowledge-base', 'show_vote_buttons', true),
                'allow_guest_search' => (bool) addon_setting('ai-knowledge-base', 'allow_guest_search', true),
                'widget_enabled' => (bool) addon_setting('ai-knowledge-base', 'widget_enabled', false),
                'widget_accent_color' => addon_setting('ai-knowledge-base', 'widget_accent_color', '#10b981'),
            ];
        });
    }
}
