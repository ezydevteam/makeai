<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GdprSettingsController extends Controller
{
    public function edit()
    {
        return Inertia::render('Admin/Settings/Gdpr', [
            'gdpr' => [
                'enabled' => (bool) settings('gdpr_enabled', false),
                'eu_only' => (bool) settings('gdpr_eu_only', true),
                'banner_position' => settings('gdpr_banner_position', 'bottom'),
                'banner_title' => settings('gdpr_banner_title', 'Cookie Preferences'),
                'banner_description' => settings('gdpr_banner_description', 'We use cookies to enhance your experience, analyze site usage, and show relevant content.'),
                'banner_accept_all_text' => settings('gdpr_banner_accept_all_text', 'Accept All'),
                'banner_customize_text' => settings('gdpr_banner_customize_text', 'Customize'),
                'banner_necessary_text' => settings('gdpr_banner_necessary_text', 'Necessary Only'),
                'banner_save_text' => settings('gdpr_banner_save_text', 'Save Preferences'),
                'banner_bg_color' => settings('gdpr_banner_bg_color', '#ffffff'),
                'banner_text_color' => settings('gdpr_banner_text_color', '#374151'),
                'banner_button_color' => settings('gdpr_banner_button_color', '#4f46e5'),
                'banner_button_text_color' => settings('gdpr_banner_button_text_color', '#ffffff'),
                'show_policy_links' => (bool) settings('gdpr_show_policy_links', true),
                'privacy_policy_url' => settings('site_privacy_url', '/privacy-policy'),
                'cookie_policy_url' => settings('gdpr_cookie_policy_url', '/cookie-policy'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        abort_unless(
            auth('admin')->user()?->hasAnyPermission(['settings.gdpr', 'settings.manage']),
            403
        );

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'eu_only' => ['required', 'boolean'],
            'banner_position' => ['required', 'string', 'in:bottom,top,center'],
            'banner_title' => ['required', 'string', 'max:200'],
            'banner_description' => ['required', 'string', 'max:500'],
            'banner_accept_all_text' => ['required', 'string', 'max:80'],
            'banner_customize_text' => ['required', 'string', 'max:80'],
            'banner_necessary_text' => ['required', 'string', 'max:80'],
            'banner_save_text' => ['required', 'string', 'max:80'],
            'banner_bg_color' => ['required', 'string', 'max:20'],
            'banner_text_color' => ['required', 'string', 'max:20'],
            'banner_button_color' => ['required', 'string', 'max:20'],
            'banner_button_text_color' => ['required', 'string', 'max:20'],
            'show_policy_links' => ['required', 'boolean'],
            'privacy_policy_url' => ['nullable', 'string', 'max:500'],
            'cookie_policy_url' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($validated as $key => $value) {
            $prefix = $key === 'privacy_policy_url' ? 'site' : 'gdpr';
            $settingKey = $key === 'privacy_policy_url' ? 'site_privacy_url' : "gdpr_{$key}";

            settings_set(
                $settingKey,
                $value,
                is_bool($value) ? 'boolean' : 'string',
                $prefix === 'site' ? 'branding' : 'gdpr'
            );
        }

        return back()->with('success', __('GDPR settings saved.'));
    }
}
