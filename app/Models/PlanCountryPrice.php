<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanCountryPrice extends Model
{
    protected $fillable = [
        'plan_id',
        'country_code',
        'currency_code',
        'original_price_monthly',
        'original_price_yearly',
        'original_price_lifetime',
        'price_monthly',
        'price_yearly',
        'price_lifetime',
        'vat_percentage',
        'trial_monthly_enabled',
        'trial_yearly_enabled',
        'trial_lifetime_enabled',
        'trial_monthly_days',
        'trial_yearly_days',
        'trial_lifetime_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'price_lifetime' => 'decimal:2',
            'original_price_monthly' => 'decimal:2',
            'original_price_yearly' => 'decimal:2',
            'original_price_lifetime' => 'decimal:2',
            'vat_percentage' => 'decimal:2',
            'trial_monthly_enabled' => 'boolean',
            'trial_yearly_enabled' => 'boolean',
            'trial_lifetime_enabled' => 'boolean',
            'trial_monthly_days' => 'integer',
            'trial_yearly_days' => 'integer',
            'trial_lifetime_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
