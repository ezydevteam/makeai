<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GeneralSettingsRequest;
use App\Models\Currency;
use App\Models\Language;
use Inertia\Inertia;

class GeneralSettingsController extends Controller
{
    public function edit()
    {
        $this->authorizeGeneralSettings();

        return Inertia::render('Admin/Settings/General', [
            'settings' => [
                'app_name' => settings('app_name', 'Application'),
                'app_url' => settings('app_url', url('/')),
                'default_language' => settings('default_language', 'en'),
                'default_currency' => settings('default_currency', 'USD'),
                'currency_symbol' => settings('currency_symbol', '$'),
                'currency_position' => settings('currency_position', 'before'),
                'currency_decimals' => (int) settings('currency_decimals', 2),
                'app_timezone' => settings('app_timezone', config('app.timezone', 'UTC')),
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
        ]);
    }

    public function update(GeneralSettingsRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            settings_set($key, $value, is_int($value) ? 'integer' : 'string', 'general');
        }

        return back()->with('success', translate('General settings saved.'));
    }

    private function authorizeGeneralSettings(): void
    {
        abort_unless(auth('admin')->user()?->hasAnyPermission(['settings.general', 'settings.manage']), 403);
    }
}
