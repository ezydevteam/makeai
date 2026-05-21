<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AffiliateProgram extends Model
{
    protected $fillable = [
        'is_active',
        'commission_type',
        'commission_value',
        'commission_on',
        'cookie_days',
        'min_payout',
        'payouts_enabled',
        'payout_methods',
        'auto_approve_commissions',
        'commission_hold_days',
        'allow_custom_alias',
        'marketing_banners',
        'promotional_emails',
        'social_posts',
        'terms',
        'terms_page_slug',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'commission_value' => 'decimal:2',
            'cookie_days' => 'integer',
            'min_payout' => 'decimal:2',
            'payouts_enabled' => 'boolean',
            'payout_methods' => 'array',
            'auto_approve_commissions' => 'boolean',
            'commission_hold_days' => 'integer',
            'allow_custom_alias' => 'boolean',
            'marketing_banners' => 'array',
            'promotional_emails' => 'array',
            'social_posts' => 'array',
        ];
    }

    public static function current(): self
    {
        if (! Schema::hasTable('affiliate_programs')) {
            return new static(static::defaults());
        }

        return static::query()->firstOrCreate([], [
            ...static::defaults(),
        ]);
    }

    private static function defaults(): array
    {
        return [
            'is_active' => false,
            'commission_type' => 'percentage',
            'commission_value' => 20,
            'commission_on' => 'first_purchase',
            'cookie_days' => 30,
            'min_payout' => 20,
            'payouts_enabled' => true,
            'payout_methods' => ['paypal', 'bank_transfer', 'credits'],
            'auto_approve_commissions' => false,
            'commission_hold_days' => 14,
            'allow_custom_alias' => false,
            'marketing_banners' => [],
            'promotional_emails' => [],
            'social_posts' => [],
            'terms' => '',
            'terms_page_slug' => null,
        ];
    }
}
