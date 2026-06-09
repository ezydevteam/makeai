<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AffiliateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'is_active' => ['boolean'],
            'commission_type' => ['required', 'in:percentage,fixed'],
            'commission_value' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'commission_on' => ['required', 'in:first_purchase,all_purchases,subscription'],
            'cookie_days' => ['required', 'integer', 'min:1', 'max:365'],
            'min_payout' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'payouts_enabled' => ['boolean'],
            'payout_methods' => ['array'],
            'payout_methods.*' => ['in:paypal,bank_transfer,credits'],
            'auto_approve_commissions' => ['boolean'],
            'referral_credits_enabled' => ['boolean'],
            'referral_credits_amount' => ['required', 'numeric', 'min:0', 'max:999999.9999'],
            'commission_hold_days' => ['required', 'integer', 'min:0', 'max:365'],
            'allow_custom_alias' => ['boolean'],
            'terms_page_slug' => ['nullable', 'string', 'max:255', 'exists:pages,slug'],
            'marketing_banners' => ['nullable', 'array'],
            'marketing_banners.*.url' => ['nullable', 'url', 'max:2048'],
            'marketing_banners.*.label' => ['nullable', 'string', 'max:255'],
            'promotional_emails' => ['nullable', 'array'],
            'promotional_emails.*.subject' => ['nullable', 'string', 'max:255'],
            'promotional_emails.*.body' => ['nullable', 'string', 'max:10000'],
            'social_posts' => ['nullable', 'array'],
            'social_posts.*.text' => ['nullable', 'string', 'max:1000'],
            'social_posts.*.platform' => ['nullable', 'string', 'max:50'],
        ];
    }
}
