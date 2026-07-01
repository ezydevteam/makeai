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
            'subscriptions_enabled' => ['required', 'boolean'],
            'affiliate_enabled' => ['required', 'boolean'],
            'tickets_enabled' => ['required', 'boolean'],
            'contact_enabled' => ['required', 'boolean'],
            'blog_enabled' => ['required', 'boolean'],
            'notifications_enabled' => ['required', 'boolean'],
            'registration_enabled' => ['required', 'boolean'],
            'email_verification_enabled' => ['required', 'boolean'],
            'tools_review_approval_enabled' => ['required', 'boolean'],
        ];
    }
}
