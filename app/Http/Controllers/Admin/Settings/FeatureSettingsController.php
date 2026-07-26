<?php

namespace App\Http\Controllers\Admin\Settings;

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
                'subscriptions_enabled' => is_extended_license() && (bool) settings('subscriptions_enabled', true),
                'coupons_enabled' => is_extended_license() && (bool) settings('coupons_enabled', true),
                'affiliate_enabled' => is_extended_license() && (bool) settings('affiliate_enabled', true),
                'tickets_enabled' => (bool) settings('tickets_enabled', true),
                'contact_enabled' => (bool) settings('contact_enabled', true),
                'blog_enabled' => (bool) settings('blog_enabled', true),
                'notifications_enabled' => (bool) settings('notifications_enabled', true),
                'registration_enabled' => (bool) settings('registration_enabled', true),
                'email_verification_enabled' => (bool) settings('email_verification_enabled', true),
                'onboarding_enabled' => (bool) settings('onboarding_enabled', true),
                'tools_review_approval_enabled' => (bool) settings('tools_review_approval_enabled', true),
                'byok_enabled' => (bool) settings('byok_enabled', true),
                'account_deletion_enabled' => (bool) settings('account_deletion_enabled', true),
                'playground_enabled' => (bool) settings('playground_enabled', true),
                'chains_enabled' => (bool) settings('chains_enabled', true),
                'tool_embeds_enabled' => (bool) settings('tool_embeds_enabled', true),
                // Shares its source of truth with the Appearance › Tool Page toggle —
                // `global_tools_*` keys are routed to the theme settings blob by the
                // settings()/settings_set() helpers, so both screens edit one value.
                'global_tools_brand_voice_enabled' => (bool) settings('global_tools_brand_voice_enabled', true),
                'optin_preferences_enabled' => (bool) settings('optin_preferences_enabled', true),
                'cookie_preferences_enabled' => (bool) settings('cookie_preferences_enabled', true),
                'phone_required' => (bool) settings('phone_required', false),
                'two_factor_required' => (bool) settings('two_factor_required', false),
                'two_factor_sms_enabled' => (bool) settings('two_factor_sms_enabled', false),
            ],
        ]);
    }

    public function update(FeatureSettingsRequest $request)
    {
        $this->authorizeSettings();

        $features = [
            'tickets_enabled',
            'contact_enabled',
            'blog_enabled',
            'notifications_enabled',
            'registration_enabled',
            'email_verification_enabled',
            'onboarding_enabled',
            'tools_review_approval_enabled',
            'byok_enabled',
            'account_deletion_enabled',
            'playground_enabled',
            'chains_enabled',
            'tool_embeds_enabled',
            // Independent of each other: SMS can be an offered 2FA method whether or
            // not 2FA is mandatory.
            'optin_preferences_enabled',
            'cookie_preferences_enabled',
            'phone_required',
            'two_factor_required',
            'two_factor_sms_enabled',
        ];

        foreach ($features as $feature) {
            settings_set($feature, (bool) $request->validated($feature), 'boolean', 'features');
        }

        // Not a `features` group row: settings_set() routes `global_tools_*` keys into
        // the theme tool-page blob, which is also what Appearance › Tool Page writes.
        settings_set(
            'global_tools_brand_voice_enabled',
            (bool) $request->validated('global_tools_brand_voice_enabled'),
            'boolean',
        );

        // Premium subscriptions AND the affiliate program require an Extended
        // License (both are monetization features). Enforced server-side — hiding
        // the toggle in the UI alone would not stop a direct POST.
        $subscriptionsEnabled = is_extended_license() && (bool) $request->validated('subscriptions_enabled', false);
        settings_set('subscriptions_enabled', $subscriptionsEnabled, 'boolean', 'features');

        $couponsEnabled = is_extended_license() && (bool) $request->validated('coupons_enabled', true);
        settings_set('coupons_enabled', $couponsEnabled, 'boolean', 'features');

        $affiliateEnabled = is_extended_license() && (bool) $request->validated('affiliate_enabled', false);
        settings_set('affiliate_enabled', $affiliateEnabled, 'boolean', 'features');

        return back()->with('success', translate('Feature settings updated.'));
    }

    private function authorizeSettings(): void
    {
        abort_unless(auth('admin')->user()?->hasAnyPermission(['settings.general', 'settings.manage']), 403);
    }
}
