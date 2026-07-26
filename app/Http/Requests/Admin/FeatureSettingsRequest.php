<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FeatureSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            // Only submitted on Extended License installs — the toggle is hidden otherwise.
            'subscriptions_enabled' => ['sometimes', 'boolean'],
            // Only submitted on Extended License installs — the toggle is hidden otherwise.
            'coupons_enabled' => ['sometimes', 'boolean'],
            'affiliate_enabled' => ['required', 'boolean'],
            'tickets_enabled' => ['required', 'boolean'],
            'contact_enabled' => ['required', 'boolean'],
            'blog_enabled' => ['required', 'boolean'],
            'notifications_enabled' => ['required', 'boolean'],
            'registration_enabled' => ['required', 'boolean'],
            'email_verification_enabled' => ['required', 'boolean'],
            'onboarding_enabled' => ['required', 'boolean'],
            'tools_review_approval_enabled' => ['required', 'boolean'],
            'byok_enabled' => ['required', 'boolean'],
            'account_deletion_enabled' => ['required', 'boolean'],
            'playground_enabled' => ['required', 'boolean'],
            'chains_enabled' => ['required', 'boolean'],
            'tool_embeds_enabled' => ['required', 'boolean'],
            'global_tools_brand_voice_enabled' => ['required', 'boolean'],
            'optin_preferences_enabled' => ['required', 'boolean'],
            'cookie_preferences_enabled' => ['required', 'boolean'],
            'phone_required' => ['required', 'boolean'],
            'two_factor_required' => ['required', 'boolean'],
            'two_factor_sms_enabled' => ['required', 'boolean'],
        ];
    }
}
