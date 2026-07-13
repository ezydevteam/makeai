<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AffiliateAliasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Slugs that would collide with real routes/paths or impersonate the system.
     */
    private const RESERVED_SLUGS = [
        'admin', 'api', 'register', 'login', 'logout', 'ref', 'affiliate',
        'dashboard', 'user', 'account', 'settings', 'billing', 'checkout',
        'pricing', 'support', 'null', 'undefined', 'www', 'app',
    ];

    public function rules(): array
    {
        return [
            'custom_slug' => [
                'nullable',
                'string',
                'min:3',
                'max:60',
                'regex:/^[a-z0-9-]+$/',
                Rule::notIn(self::RESERVED_SLUGS),
                // Must be unique among aliases AND must not collide with any user's
                // referral_code — findReferrer() matches either column, so without this
                // an alias could hijack another affiliate's referral link.
                Rule::unique('users', 'affiliate_custom_slug')->ignore($this->user()?->id),
                Rule::unique('users', 'referral_code'),
            ],
        ];
    }

    public function prepareForValidation(): void
    {
        // Normalise so uniqueness/reserved checks are case-insensitive and trimmed.
        if ($this->has('custom_slug') && is_string($this->input('custom_slug'))) {
            $this->merge(['custom_slug' => trim(strtolower($this->input('custom_slug'))) ?: null]);
        }
    }
}
