<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GatewaySubscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $table = 'billing_subscriptions';

    protected $fillable = [
        'user_id', 'plan_id', 'billing_cycle', 'status', 'gateway',
        'gateway_subscription_id', 'amount', 'currency',
        'trial_ends_at', 'current_period_start', 'current_period_end', 'cancelled_at',
        'credits_refreshed_at',
        'scheduled_plan_id', 'scheduled_billing_cycle', 'scheduled_change_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
            'credits_refreshed_at' => 'datetime',
            'scheduled_change_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function scheduledPlan()
    {
        return $this->belongsTo(Plan::class, 'scheduled_plan_id');
    }

    public function hasScheduledChange(): bool
    {
        return $this->scheduled_plan_id !== null && $this->scheduled_change_at !== null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active' || $this->status === 'trialing';
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing' && $this->trial_ends_at?->isFuture();
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    public function isWithinGracePeriod(): bool
    {
        return $this->isCancelled() && $this->current_period_end?->isFuture();
    }
}
