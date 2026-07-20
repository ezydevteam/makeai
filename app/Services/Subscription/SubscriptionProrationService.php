<?php

namespace App\Services\Subscription;

use App\Models\GatewaySubscription;
use App\Models\Plan;
use App\Models\User;

/**
 * Plan-change math: classifies a target plan against the current one and computes
 * the proration credit granted toward an upgrade.
 *
 * Proration model (chosen by the product owner): "unused credits only" — the
 * credit is the proportion of the plan's credit grant the user has NOT consumed
 * this period, valued against what they paid for the current period.
 */
class SubscriptionProrationService
{
    public const UPGRADE = 'upgrade';

    public const DOWNGRADE = 'downgrade';

    public const SAME = 'same';

    /**
     * Billing-cycle commitment order: a longer cycle is a "higher" move.
     */
    public const CYCLE_RANK = [
        'monthly' => 1,
        'yearly' => 2,
        'lifetime' => 3,
    ];

    /**
     * Classify a (plan, cycle) change relative to the user's current (plan, cycle).
     * A different plan ranks by plan tier; the SAME plan on a different billing
     * cycle ranks by cycle length (monthly < yearly < lifetime) — moving to a
     * longer cycle is an upgrade (pay now), a shorter one a downgrade (schedule).
     */
    public function classifyChange(?Plan $current, ?string $currentCycle, Plan $target, string $targetCycle): string
    {
        if (! $current) {
            return self::UPGRADE;
        }

        if ($current->id !== $target->id) {
            return $this->classify($current, $target);
        }

        $from = self::CYCLE_RANK[$currentCycle] ?? 1;
        $to = self::CYCLE_RANK[$targetCycle] ?? 1;

        if ($to === $from) {
            return self::SAME;
        }

        return $to > $from ? self::UPGRADE : self::DOWNGRADE;
    }

    /**
     * Classify a target plan relative to the current plan.
     *
     * Tier order follows plans.sort_order (higher = more premium), with
     * price_monthly as the tiebreaker when sort_order is equal.
     */
    public function classify(?Plan $current, Plan $target): string
    {
        // A free user (no current paid plan) can only move up.
        if (! $current) {
            return self::UPGRADE;
        }

        if ($current->id === $target->id) {
            return self::SAME;
        }

        $currentRank = [(int) $current->sort_order, (float) $current->price_monthly];
        $targetRank = [(int) $target->sort_order, (float) $target->price_monthly];

        if ($targetRank === $currentRank) {
            return self::SAME;
        }

        return $targetRank > $currentRank ? self::UPGRADE : self::DOWNGRADE;
    }

    /**
     * Fraction of the current plan's credit grant still unused this period, in
     * [0, 1]. Falls back to a time-based ratio when the plan has no credit grant
     * (unlimited / zero-credit plans) so proration still behaves sensibly.
     */
    public function unusedCreditRatio(User $user, Plan $currentPlan, ?GatewaySubscription $subscription = null): float
    {
        $grant = (float) $currentPlan->credits;

        // The credit-based ratio uses credits_used_month, a CALENDAR-month counter
        // reset on the 1st. It only reflects the true period usage for MONTHLY billing.
        // For yearly/lifetime (or an unknown cycle), fall back to the time-based ratio,
        // otherwise a mid-period reset would credit near-full unused for a heavily-used
        // annual plan.
        $isMonthly = $subscription === null || ($subscription->billing_cycle ?? 'monthly') === 'monthly';

        if ($grant <= 0 || ! $isMonthly) {
            return $this->timeRatio($subscription);
        }

        $used = (float) $user->credits_used_month;
        $ratio = ($grant - $used) / $grant;

        return max(0.0, min(1.0, $ratio));
    }

    /**
     * Money credited toward an upgrade: the unused-credit fraction of what the
     * user paid for the current period. Zero for free/trial periods (nothing
     * was paid).
     */
    public function prorationCredit(GatewaySubscription $subscription): float
    {
        $paid = (float) $subscription->amount;

        if ($paid <= 0 || ! $subscription->user || ! $subscription->plan) {
            return 0.0;
        }

        $ratio = $this->unusedCreditRatio($subscription->user, $subscription->plan, $subscription);

        return round(min($paid, $ratio * $paid), 2);
    }

    /**
     * Portion of the current period still remaining, in [0, 1]. Lifetime/undated
     * periods count as fully remaining.
     */
    protected function timeRatio(?GatewaySubscription $subscription): float
    {
        if (! $subscription || ! $subscription->current_period_end) {
            return 1.0;
        }

        $start = $subscription->current_period_start ?? $subscription->created_at;

        if (! $start) {
            return 1.0;
        }

        $total = $start->diffInSeconds($subscription->current_period_end, false);
        $remaining = now()->diffInSeconds($subscription->current_period_end, false);

        if ($total <= 0) {
            return 0.0;
        }

        return max(0.0, min(1.0, $remaining / $total));
    }
}
