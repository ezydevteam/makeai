<?php

namespace Addons\PublicKnowledgeBase;

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
            if (! is_addon_active('public-knowledge-base')) {
                return null;
            }

            return [
                'enabled'      => (bool) addon_setting('public-knowledge-base', 'enabled', true),
                'public_slug'  => addon_setting('public-knowledge-base', 'public_slug', 'help'),
                'page_title'   => addon_setting('public-knowledge-base', 'page_title', 'Help Center'),
                'page_description' => addon_setting('public-knowledge-base', 'page_description', ''),
                'show_vote_buttons' => (bool) addon_setting('public-knowledge-base', 'show_vote_buttons', true),
                'allow_guest_search' => (bool) addon_setting('public-knowledge-base', 'allow_guest_search', true),
                'widget_enabled' => (bool) addon_setting('public-knowledge-base', 'widget_enabled', false),
                'widget_accent_color' => addon_setting('public-knowledge-base', 'widget_accent_color', '#10b981'),
            ];
        });
    }
}
