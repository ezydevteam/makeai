<?php

namespace Addons\SocialScheduler\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SsAdminSettingsController extends Controller
{
    public function edit(): \Inertia\Response
    {
        return inertia('Addons/social-scheduler/Admin/Settings', [
            'settings' => [
                'enabled' => addon_setting('social-scheduler', 'enabled', true),
                'approval_required' => addon_setting('social-scheduler', 'approval_required', false),
                'max_accounts_per_user' => addon_setting('social-scheduler', 'max_accounts_per_user', 10),
                'max_media_mb' => addon_setting('social-scheduler', 'max_media_mb', 50),
                'ai_model' => addon_setting('social-scheduler', 'ai_model', ''),
                'best_time_model' => addon_setting('social-scheduler', 'best_time_model', ''),
                'rss_poll_interval_minutes' => addon_setting('social-scheduler', 'rss_poll_interval_minutes', 60),
                'analytics_pull_enabled' => addon_setting('social-scheduler', 'analytics_pull_enabled', true),
                'carousel_max_slides' => addon_setting('social-scheduler', 'carousel_max_slides', 10),
                'first_comment_enabled' => addon_setting('social-scheduler', 'first_comment_enabled', true),
                'provider' => addon_setting('social-scheduler', 'provider', ''),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['boolean'],
            'approval_required' => ['boolean'],
            'max_accounts_per_user' => ['integer', 'min:1', 'max:50'],
            'max_media_mb' => ['integer', 'min:1', 'max:500'],
            'ai_model' => ['nullable', 'string', 'max:100'],
            'best_time_model' => ['nullable', 'string', 'max:100'],
            'rss_poll_interval_minutes' => ['integer', 'min:5', 'max:1440'],
            'analytics_pull_enabled' => ['boolean'],
            'carousel_max_slides' => ['integer', 'min:2', 'max:20'],
            'first_comment_enabled' => ['boolean'],
            'provider' => ['nullable', 'string', 'max:50'],
        ]);

        foreach ($validated as $key => $value) {
            addon_setting_set('social-scheduler', $key, $value);
        }

        return back()->with('flash', 'Settings saved.');
    }
}
