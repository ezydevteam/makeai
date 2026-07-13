<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Coupon — discount codes for subscriptions (Part 6).
 */
class Coupon extends Model
{
    /**
     * How long a paid-checkout coupon reservation is held before it self-expires.
     * Long enough to complete a hosted-gateway payment; short enough that an
     * abandoned checkout frees the slot for a legitimate retry.
     */
    public const RESERVATION_TTL_MINUTES = 20;

    protected $fillable = [
        'code', 'type', 'value', 'max_discount', 'max_uses', 'per_user_limit',
        'used_count', 'is_recurring', 'plan_id', 'user_limit', 'show_in_header',
        'starts_at', 'expires_at', 'is_active', 'activated_at', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'per_user_limit' => 'integer',
            'is_recurring' => 'boolean',
            'is_active' => 'boolean',
            'show_in_header' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Coupon $coupon) {
            // Stamp when a coupon is (de)activated or (un)published so admin
            // analytics can measure real week-over-week movement — the table has
            // no status history otherwise. Covers create + model updates; the
            // query-builder mass-demotion in toggleHeader() clears published_at
            // itself since it bypasses model events.
            if ($coupon->isDirty('is_active')) {
                $coupon->activated_at = $coupon->is_active ? now() : null;
            }
            if ($coupon->isDirty('show_in_header')) {
                $coupon->published_at = $coupon->show_in_header ? now() : null;
            }
        });
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function redemptions()
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * How many times this user has already redeemed this coupon.
     */
    public function userRedemptionCount(User $user): int
    {
        return $this->redemptions()->where('user_id', $user->id)->count();
    }

    /**
     * Whether the user has hit this coupon's per-user redemption limit.
     * A NULL per_user_limit means unlimited redemptions per user.
     */
    public function hasReachedUserLimit(User $user): bool
    {
        if ($this->per_user_limit === null) {
            return false;
        }

        return $this->userRedemptionCount($user) >= (int) $this->per_user_limit;
    }

    /**
     * Atomically hold a per-user redemption slot for a paid checkout in flight.
     *
     * Recorded redemptions are only written at activation, so concurrent paid
     * checkouts could each pass hasReachedUserLimit() and both take the discount.
     * This claims one of the remaining (limit − recorded) slots via an atomic
     * Cache::add, so only one concurrent request can win each free slot.
     *
     * @return string|null The reservation key to release later, or null when no
     *                     slot is available (treat as "already used").
     */
    public function reserveForUser(User $user): ?string
    {
        if ($this->per_user_limit === null) {
            return null; // unlimited — no reservation needed or possible
        }

        $available = (int) $this->per_user_limit - $this->userRedemptionCount($user);

        for ($slot = 0; $slot < $available; $slot++) {
            $key = "coupon_reservation:{$this->id}:{$user->id}:{$slot}";

            if (Cache::add($key, true, now()->addMinutes(self::RESERVATION_TTL_MINUTES))) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Release a previously held reservation key (see reserveForUser). Best-effort:
     * reservations self-expire, so a missed release only briefly delays reuse.
     */
    public static function releaseReservation(?string $key): void
    {
        if ($key) {
            Cache::forget($key);
        }
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

    public function isEligibleForUser(User $user): bool
    {
        return match ($this->user_limit) {
            'active' => $user->is_active, // Note: 'is_active' means non-banned in this codebase
            'inactive' => ! $user->is_active,
            'free' => ! $user->isPro(),
            'pro' => $user->isPro(),
            'recent_30_days' => $user->created_at?->greaterThanOrEqualTo(now()->subDays(30)) ?? false,
            default => true,
        };
    }

    /**
     * Get a clear, user-friendly label for the user_limit option.
     * Resolves ambiguity (e.g., 'active' means 'non-banned', not 'has active subscription').
     */
    public function getUserLimitLabelAttribute(): string
    {
        return match ($this->user_limit) {
            'active' => 'Non-banned users',
            'inactive' => 'Banned/Suspended users',
            'free' => 'Free tier users',
            'pro' => 'Pro/Paid users',
            'recent_30_days' => 'Users registered in last 30 days',
            default => 'All users',
        };
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

    /**
     * Atomically claim a GLOBAL usage slot at checkout time (before charging), so a
     * max_uses-limited coupon can't be over-redeemed by concurrent checkouts that all
     * pass the non-locking isValid() check. Increments used_count only while a slot
     * remains; an unlimited (null max_uses) coupon always succeeds. Release with
     * releaseGlobalUse() if the checkout later fails.
     *
     * @return bool true when a slot was claimed; false when the coupon is exhausted.
     */
    public function reserveGlobalUse(): bool
    {
        $affected = static::whereKey($this->id)
            ->where(function ($query) {
                $query->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses');
            })
            ->increment('used_count');

        return $affected > 0;
    }

    /**
     * Release a global slot claimed by reserveGlobalUse() when its checkout failed
     * before completing. Floored at zero so it can never go negative.
     */
    public function releaseGlobalUse(): void
    {
        static::whereKey($this->id)->where('used_count', '>', 0)->decrement('used_count');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
