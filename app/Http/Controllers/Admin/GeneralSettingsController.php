<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GeneralSettingsRequest;
use App\Models\Currency;
use App\Models\Language;
use App\Models\SiteTemplate;
use Inertia\Inertia;

class GeneralSettingsController extends Controller
{
    public function edit()
    {
        $this->authorizeGeneralSettings();

        return Inertia::render('Admin/Settings/General', [
            'settings' => [
                'site_name'          => settings('site_name', translate('Application')),
                'site_tagline'       => settings('site_tagline', ''),
                'site_description'   => settings('site_description', ''),
                'site_support_email' => settings('site_support_email', ''),
                'site_support_url'   => settings('site_support_url', ''),
                'site_terms_url'     => settings('site_terms_url', ''),
                'site_privacy_url'   => settings('site_privacy_url', ''),

                'site_url'          => settings('site_url', url('/')),
                'default_language'  => settings('default_language', 'en'),
                'default_currency'  => settings('default_currency', 'USD'),
                'currency_symbol'   => settings('currency_symbol', '$'),
                'currency_position' => settings('currency_position', 'before'),
                'currency_decimals' => (int) settings('currency_decimals', 2),
                'app_timezone'      => settings('app_timezone', config('app.timezone', 'UTC')),
                'homepage_template' => settings('homepage_template', 'default'),
            ],
            'languages' => Language::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['code', 'name']),
            'currencies' => Currency::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('code')
                ->get(['code', 'name', 'symbol']),
            'timezones' => timezone_identifiers_list(),
            'homepage_templates' => SiteTemplate::active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['slug', 'name', 'requires_pro'])
                ->map(fn ($t) => [
                    'slug' => $t->slug,
                    'name' => $t->name,
                    'requires_pro' => (bool) $t->requires_pro,
                ])
                ->values(),
        ]);
    }

    public function update(GeneralSettingsRequest $request)
    {
        $validated = $request->validated();

        foreach ($validated as $key => $value) {
            $type = match (true) {
                is_int($value) => 'integer',
                is_bool($value) => 'boolean',
                default => 'string',
            };

            $brandingKeys = [
                'site_name', 'site_tagline', 'site_description',
                'site_support_email', 'site_support_url',
                'site_terms_url', 'site_privacy_url',
            ];

            $group = in_array($key, $brandingKeys) ? 'branding' : 'general';
            settings_set($key, $value, $type, $group);
        }

        return back()->with('success', translate('General settings saved.'));
    }

    private function authorizeGeneralSettings(): void
    {
        abort_unless(auth('admin')->user()?->hasAnyPermission(['settings.general', 'settings.manage']), 403);
    }
}
