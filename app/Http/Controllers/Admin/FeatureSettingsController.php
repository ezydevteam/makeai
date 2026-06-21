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
                'favorites_enabled' => (bool) settings('favorites_enabled', true),
                'subscriptions_enabled' => (bool) settings('subscriptions_enabled', false),
                'affiliate_enabled' => (bool) settings('affiliate_enabled', false),
                'tickets_enabled' => (bool) settings('tickets_enabled', true),
                'contact_enabled' => (bool) settings('contact_enabled', true),
                'blog_enabled' => (bool) settings('blog_enabled', true),
                'notifications_enabled' => (bool) settings('notifications_enabled', true),
                'registration_enabled' => (bool) settings('registration_enabled', true),
                'email_verification_enabled' => (bool) settings('email_verification_enabled', true),
            ],
        ]);
    }

    public function update(FeatureSettingsRequest $request)
    {
        $this->authorizeSettings();

        $features = [
            'subscriptions_enabled',
            'affiliate_enabled',
            'tickets_enabled',
            'contact_enabled',
            'blog_enabled',
            'notifications_enabled',
            'registration_enabled',
            'email_verification_enabled',
        ];

        foreach ($features as $feature) {
            settings_set($feature, (bool) $request->validated($feature), 'boolean', 'features');
        }

        return back()->with('success', translate('Feature settings updated.'));
    }

    private function authorizeSettings(): void
    {
        abort_unless(auth('admin')->user()?->hasAnyPermission(['settings.general', 'settings.manage']), 403);
    }
}
