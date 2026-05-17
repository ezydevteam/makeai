<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'price_monthly', 'price_yearly', 'price_lifetime',
        'credits', 'features', 'ai_models', 'max_tokens_per_request',
        'daily_token_limit', 'max_images_per_day', 'max_chats',
        'is_featured', 'is_active', 'is_free', 'trial_days', 'sort_order',
        'stripe_price_monthly_id', 'stripe_price_yearly_id',
        'paypal_plan_monthly_id', 'paypal_plan_yearly_id',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'price_lifetime' => 'decimal:2',
            'credits' => 'decimal:2',
            'features' => 'array',
            'ai_models' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'is_free' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Plan $plan) {
            if (empty($plan->slug)) {
                $plan->slug = Str::slug($plan->name);
            }
        });
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get yearly savings percentage.
     */
    public function getYearlySavingsAttribute(): int
    {
        if ($this->price_monthly <= 0) {
            return 0;
        }
        $yearlyIfMonthly = $this->price_monthly * 12;
        if ($yearlyIfMonthly <= 0) {
            return 0;
        }

        return (int) round((1 - ($this->price_yearly / $yearlyIfMonthly)) * 100);
    }
}
