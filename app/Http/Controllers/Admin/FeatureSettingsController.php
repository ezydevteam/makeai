<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FeatureSettingsRequest;
use Inertia\Inertia;
use Inertia\Response;

class FeatureSettingsController extends Controller
{
    public function edit(): Response
    {
        $this->authorizeSettings();

        return Inertia::render('Admin/Settings/Features', [
            'features' => [
                'scroll_to_top_enabled' => (bool) settings('scroll_to_top_enabled', true),
            ],
        ]);
    }

    public function update(FeatureSettingsRequest $request)
    {
        $this->authorizeSettings();

        settings_set('scroll_to_top_enabled', (bool) $request->validated('scroll_to_top_enabled'), 'boolean', 'features');

        return back()->with('success', translate('Feature settings updated.'));
    }

    private function authorizeSettings(): void
    {
        abort_unless(auth('admin')->user()?->hasAnyPermission(['settings.general', 'settings.manage']), 403);
    }
}
