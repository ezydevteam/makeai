<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Coupon — discount codes for subscriptions (Part 6).
 */
class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'max_discount', 'max_uses',
        'used_count', 'is_recurring', 'plan_id',
        'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'is_recurring' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Check if coupon is valid and usable.
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return false;
        }
        if ($this->starts_at && now()->isBefore($this->starts_at)) {
            return false;
        }
        if ($this->expires_at && now()->isAfter($this->expires_at)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate discount amount for a given price.
     */
    public function calculateDiscount(float $price): float
    {
        if ($this->type === 'percent') {
            $discount = $price * ($this->value / 100);
            if ($this->max_discount) {
                $discount = min($discount, $this->max_discount);
            }

            return round($discount, 2);
        }

        // fixed
        return min($this->value, $price);
    }

    /**
     * Increment usage count.
     */
    public function markUsed(): void
    {
        $this->increment('used_count');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
