<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'bottom_info_text', 'price_monthly', 'price_yearly', 'price_lifetime',
        'original_price_monthly', 'original_price_yearly', 'original_price_lifetime', 'vat_percentage',
        'trial_all_countries',
        'credits', 'features',
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
            'original_price_monthly' => 'decimal:2',
            'original_price_yearly' => 'decimal:2',
            'original_price_lifetime' => 'decimal:2',
            'vat_percentage' => 'decimal:2',
            'credits' => 'decimal:2',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'is_free' => 'boolean',
            'trial_all_countries' => 'boolean',
        ];
    }

    /**
     * Features, always as a list of strings.
     *
     * Seeded rows were written with json_encode() into what was already an 'array' cast, so the
     * column holds a JSON string wrapping a JSON array. A plain array cast decodes that once and
     * hands back a string — which callers then iterate character by character. Decode until we
     * reach an array so both the double-encoded rows and correctly written ones behave the same.
     */
    protected function features(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): array {
                $decoded = $value;

                for ($i = 0; $i < 2 && is_string($decoded); $i++) {
                    $decoded = json_decode($decoded, true);
                }

                if (! is_array($decoded)) {
                    return [];
                }

                return array_values(array_filter(
                    array_map(fn ($feature) => is_string($feature) ? trim($feature) : null, $decoded),
                    fn (?string $feature) => $feature !== null && $feature !== '',
                ));
            },
            set: fn (mixed $value): string => json_encode(array_values(array_filter(
                is_array($value) ? $value : [],
                fn ($feature) => is_string($feature) && trim($feature) !== '',
            ))),
        );
    }

    protected static function booted(): void
    {
        static::creating(function (Plan $plan) {
            if (empty($plan->slug)) {
                $plan->slug = Str::slug($plan->name);
            }
        });
    }

    public function countryPrices()
    {
        return $this->hasMany(PlanCountryPrice::class);
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
