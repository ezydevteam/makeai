<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GeneralSettingsRequest;
use App\Models\Currency;
use App\Models\Language;
use App\Support\CurrencyCatalog;
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
                'default_language'  => Language::defaultCode(),
                'default_currency'  => settings('default_currency', 'USD'),
                'currency_symbol'   => settings('currency_symbol', '$'),
                'currency_position' => settings('currency_position', 'before'),
                'currency_decimals' => (int) settings('currency_decimals', 2),
                'app_timezone'      => settings('app_timezone', config('app.timezone', 'UTC')),
            ],
            'languages' => Language::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['code', 'name']),
            // Full popular-currency list from the static catalog (not just the few
            // rows seeded in the DB), so the picker always offers every currency.
            'currencies' => CurrencyCatalog::options(),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(GeneralSettingsRequest $request)
    {
        $validated = $request->validated();

        // The default language is NOT a settings key — it lives in the
        // languages.is_default column (single source of truth). Pull it out
        // before the KV loop and apply it as a column flip below.
        $defaultLanguage = $validated['default_language'] ?? null;
        unset($validated['default_language']);

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

        // Currency is controlled from ONE place (here). Keep the legacy pricing key
        // and the currencies table's default flag in lockstep so nothing drifts.
        if (! empty($validated['default_currency'])) {
            $base = strtoupper((string) $validated['default_currency']);
            settings_set('pricing_currency_code', $base, 'string', 'pricing');

            // Ensure a currencies row exists for the chosen code and is the sole
            // default — seeding symbol/position/decimals from the catalog so a
            // currency the buyer never manually added still formats correctly.
            if ($meta = CurrencyCatalog::get($base)) {
                Currency::query()->updateOrCreate(
                    ['code' => $base],
                    [
                        'name' => $meta['name'],
                        'symbol' => $meta['symbol'],
                        'decimal_places' => $meta['decimals'],
                        'is_active' => true,
                    ]
                );
            }

            Currency::query()->where('is_default', true)->update(['is_default' => false]);
            Currency::query()->where('code', $base)->update(['is_default' => true]);

            // Keep the default (undetected-visitor) pricing country aligned with the
            // base currency, so the storefront's default view shows the base currency
            // rather than auto-localizing it away. Real GeoIP-detected visitors still
            // localize; the admin can re-point this later if they want a different default.
            if ($country = CurrencyCatalog::country($base)) {
                settings_set('default_pricing_country', $country, 'string', 'pricing');
            }
        }

        // Apply the default-language picker as a languages.is_default column flip
        // (the authoritative store), mirroring LanguageController::setDefault.
        if ($defaultLanguage && ($target = Language::where('code', $defaultLanguage)->first()) && ! $target->is_default) {
            Language::where('is_default', true)->update(['is_default' => false]);
            $target->update(['is_default' => true, 'is_active' => true]);
            // Mass update bypasses model events; drop every cached resolution, not just
            // the default code — this also flips is_active, which the whitelist caches.
            Language::forgetResolutionCaches();
        }

        return back()->with('success', translate('General settings saved.'));
    }

    private function authorizeGeneralSettings(): void
    {
        abort_unless(auth('admin')->user()?->hasAnyPermission(['settings.general', 'settings.manage']), 403);
    }
}
