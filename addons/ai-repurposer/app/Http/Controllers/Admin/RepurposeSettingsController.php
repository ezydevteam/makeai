<?php

namespace Addons\AiRepurposer\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class RepurposeSettingsController extends Controller
{
    public function edit()
    {
        $settings = [];
        $manifest = json_decode(file_get_contents(base_path('addons/ai-repurposer/addon.json')), true);

        foreach ($manifest['settings'] ?? [] as $setting) {
            $key = $setting['key'];
            $settings[$key] = addon_setting('ai-repurposer', $key, $setting['default'] ?? null);
        }

        return Inertia::render('Addons/ai-repurposer/Admin/Settings', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $manifest = json_decode(file_get_contents(base_path('addons/ai-repurposer/addon.json')), true);

        $rules = [];
        foreach ($manifest['settings'] ?? [] as $setting) {
            $key = $setting['key'];
            $type = $setting['type'] ?? 'string';

            $rules[$key] = match ($type) {
                'boolean'  => ['boolean'],
                'integer'  => ['integer', 'min:0'],
                'select'   => ['string'],
                default    => ['nullable', 'string'],
            };
        }

        $validated = $request->validate($rules);

        foreach ($validated as $key => $value) {
            $settingDef = collect($manifest['settings'])->firstWhere('key', $key);
            $type = $settingDef['type'] ?? 'string';
            addon_setting_set('ai-repurposer', $key, $value, $type);
        }

        return back()->with('success', __('Settings saved.'));
    }
}
