<?php

namespace App\Services\Subscription;

use App\Models\GatewaySubscription;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\AffiliateService;
use App\Services\NotificationEventService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubscriptionLifecycleService
{
    public function activateFromPayment(Payment $payment, string $gatewayPaymentId, ?string $gatewaySubscriptionId = null): GatewaySubscription
    {
        // Detect a plan switch (checkout while already subscribed) BEFORE the user
        // row is updated below, so we can send an upgrade/downgrade notice instead
        // of a "welcome" one.
        $previousPlanId = $payment->user?->plan_id;
        $hadActiveAccess = in_array($payment->user?->subscription_status, ['active', 'trialing'], true);
        $isPlanChange = $hadActiveAccess && $previousPlanId && (int) $previousPlanId !== (int) $payment->plan_id;
        $previousPlan = $isPlanChange ? Plan::find($previousPlanId) : null;
        $alreadyCompleted = $payment->status === 'completed';

        // Prior recurring subscriptions this checkout replaces — cancelled at the
        // gateway after the transaction so the old plan stops billing (e.g. a
        // recurring subscriber switching to a one-time lifetime plan).
        $priorRecurringSubs = ($hadActiveAccess && ! $alreadyCompleted)
            ? GatewaySubscription::query()
                ->where('user_id', $payment->user_id)
                ->whereIn('status', [GatewaySubscription::STATUS_ACTIVE, GatewaySubscription::STATUS_TRIALING])
                ->whereNotNull('gateway_subscription_id')
                ->get()
            : collect();

        $subscription = DB::transaction(function () use ($payment, $gatewayPaymentId, $gatewaySubscriptionId, $isPlanChange, $previousPlan, $hadActiveAccess) {
            // Concurrency-safe idempotency: row-lock the payment and re-read its
            // status inside the transaction. Two webhook deliveries (or a redelivery
            // racing the first) can otherwise both see the stale in-memory
            // status='pending' and both activate → double subscription/credits.
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (! $locked || $locked->status === 'completed') {
                return $payment->subscription ?? GatewaySubscription::where('user_id', $payment->user_id)->latest()->first();
            }
            $payment->setRawAttributes($locked->getAttributes(), true);

            $metadata = $payment->metadata ?: [];
            $billingCycle = $metadata['billing_cycle'] ?? 'monthly';
            $periodEnd = $this->periodEndFor($billingCycle);

            $subscription = GatewaySubscription::create([
                'user_id' => $payment->user_id,
                'plan_id' => $payment->plan_id,
                'billing_cycle' => $billingCycle,
                'status' => 'active',
                'gateway' => $payment->gateway,
                'gateway_subscription_id' => $gatewaySubscriptionId,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
            ]);

            $payment->update([
                'status' => 'completed',
                'gateway_payment_id' => $gatewayPaymentId,
                'subscription_id' => $subscription->id,
            ]);

            // Coupon usage accounting. The global used_count is normally reserved at
            // CHECKOUT (before charging) — see CheckoutController — in which case
            // 'coupon_global_reserved' is set and we must NOT increment again here.
            // Only paths that create a payment WITHOUT a checkout-time reservation
            // (legacy / direct admin activations) fall back to the conditional bump.
            if (! empty($metadata['coupon_code'])) {
                $coupon = \App\Models\Coupon::where('code', $metadata['coupon_code'])->first();
                if ($coupon) {
                    if (empty($metadata['coupon_global_reserved'])) {
                        $incremented = \App\Models\Coupon::where('id', $coupon->id)
                            ->where(function ($q) {
                                $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses');
                            })
                            ->increment('used_count');

                        if (! $incremented) {
                            Log::warning('Coupon usage limit reached during activation', [
                                'coupon_code' => $coupon->code,
                                'payment_id' => $payment->id,
                            ]);
                        }
                    }

                    // Record the per-user redemption. insertOrIgnore is idempotent
                    // and race-safe: the unique(payment_id) index blocks a retried
                    // activation from double-counting, and the unique
                    // (coupon_id, user_unique_guard) index enforces one-per-user at
                    // the DB level for single-use coupons (guard = user_id only when
                    // per_user_limit is exactly 1; NULL lets multi-use coupons pass).
                    \Illuminate\Support\Facades\DB::table('coupon_redemptions')->insertOrIgnore([
                        'payment_id' => $payment->id,
                        'coupon_id' => $coupon->id,
                        'user_id' => $payment->user_id,
                        'user_unique_guard' => ((int) $coupon->per_user_limit === 1) ? $payment->user_id : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Mark user as having trialed if this was a trial
            if (! empty($metadata['is_trial'])) {
                $payment->user()->update(['has_trialed' => true]);
            }

            $payment->user()->update([
                'plan_id' => $payment->plan_id,
                'subscription_status' => 'active',
                'subscription_ends_at' => $periodEnd,
            ]);

            // Grant the plan's credit allowance for the new period (refreshes a
            // spent-down balance; never wipes top-ups — see User::grantPlanAllowance).
            if ($plan = Plan::find($payment->plan_id)) {
                $payment->user->grantPlanAllowance((float) $plan->credits, "Plan credits: {$plan->name}");
            }

            // Any new checkout (plan change, billing-cycle switch, or one-time
            // re-purchase) supersedes the user's prior active subscription so only
            // one is ever active.
            if ($hadActiveAccess) {
                $this->supersedePriorSubscriptions($payment->user_id, $subscription->id);
            }

            if ($isPlanChange) {
                app(NotificationEventService::class)->planChanged($payment->user, $previousPlan, $subscription->plan, $subscription);
            } else {
                app(NotificationEventService::class)->subscriptionConfirmed($payment->user, $subscription);
            }
            app(NotificationEventService::class)->paymentSuccessful($payment, $isPlanChange);

            return $subscription;
        });

        // Cancel the superseded recurring subscriptions at the gateway so they
        // stop billing. Failure-isolated — local state is already consistent.
        foreach ($priorRecurringSubs as $old) {
            if ($old->id === $subscription->id) {
                continue;
            }

            try {
                app(\App\Services\Payment\GatewaySubscriptionCanceller::class)->cancelImmediately($old);
            } catch (Throwable $e) {
                Log::warning('Failed to cancel superseded gateway subscription', [
                    'subscription_id' => $old->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->awardAffiliateRewards($payment);

        return $subscription;
    }

    /**
     * Atomically claim a per-user coupon slot for a zero-amount (free / 100%-off)
     * activation BEFORE anything is granted. Returns false when the user has
     * already reached the coupon's per-user limit, so the caller can abort
     * instead of handing out a second free subscription.
     *
     * A row lock serialises concurrent claims — two simultaneous free-coupon
     * requests cannot both slip past the per-user limit — and the redemption is
     * recorded here (keyed on payment_id) so the later activation's insertOrIgnore
     * is a no-op.
     */
    public function claimCouponRedemption(\App\Models\Coupon $coupon, Payment $payment): bool
    {
        $userId = $payment->user_id;
        $isSingleUse = ((int) $coupon->per_user_limit === 1);

        return DB::transaction(function () use ($coupon, $payment, $userId, $isSingleUse) {
            $limit = $coupon->per_user_limit; // null = unlimited per user

            if ($limit !== null) {
                $used = DB::table('coupon_redemptions')
                    ->where('coupon_id', $coupon->id)
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->count();

                if ($used >= (int) $limit) {
                    return false;
                }
            }

            $inserted = DB::table('coupon_redemptions')->insertOrIgnore([
                'payment_id' => $payment->id,
                'coupon_id' => $coupon->id,
                'user_id' => $userId,
                'user_unique_guard' => $isSingleUse ? $userId : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 0 rows => the unique guard rejected it (a concurrent claim won).
            return $inserted > 0;
        });
    }

    /**
     * Start a free trial without charging the user. Callers must verify
     * $user->has_trialed beforehand and show a friendly error if set.
     */
    public function startTrial(
        \App\Models\User $user,
        Plan $plan,
        string $billingCycle,
        string $gatewaySlug,
        int $trialDays,
        string $currencyCode,
    ): GatewaySubscription {
        $subscription = DB::transaction(function () use ($user, $plan, $billingCycle, $gatewaySlug, $trialDays, $currencyCode) {
            $trialEndsAt = now()->addDays(max(1, $trialDays));

            $subscription = GatewaySubscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'status' => GatewaySubscription::STATUS_TRIALING,
                'gateway' => $gatewaySlug,
                'amount' => 0,
                'currency' => $currencyCode,
                'trial_ends_at' => $trialEndsAt,
                'current_period_start' => now(),
                'current_period_end' => $trialEndsAt,
            ]);

            $user->update([
                'plan_id' => $plan->id,
                'subscription_status' => 'trialing',
                'trial_ends_at' => $trialEndsAt,
                'subscription_ends_at' => $trialEndsAt,
                'has_trialed' => true,
            ]);

            // A trial grants the plan's credit allowance for the trial period.
            $user->grantPlanAllowance((float) $plan->credits, "Trial credits: {$plan->name}");

            return $subscription;
        });

        app(NotificationEventService::class)->trialStarted($subscription);

        return $subscription;
    }

    public function renewFromGatewaySubscription(
        string $gateway,
        string $gatewaySubscriptionId,
        string $gatewayPaymentId,
        float $amount,
        string $currency,
    ): ?GatewaySubscription {
        $subscription = GatewaySubscription::where('gateway', $gateway)
            ->where('gateway_subscription_id', $gatewaySubscriptionId)
            ->first();

        if (! $subscription) {
            return null;
        }

        $billingCycle = $subscription->billing_cycle ?? 'monthly';
        $periodEnd = $this->periodEndFor($billingCycle);

        // Find the original payment to check for recurring coupons
        $originalPayment = Payment::where('subscription_id', $subscription->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'asc')
            ->first();

        $finalAmount = $amount;
        $renewalMetadata = [
            'billing_cycle' => $billingCycle,
            'created_from' => 'gateway_renewal_webhook',
        ];

        if ($originalPayment && ! empty($originalPayment->metadata['coupon_code'])) {
            $coupon = \App\Models\Coupon::where('code', $originalPayment->metadata['coupon_code'])->first();

            // Re-validate on every renewal: a recurring coupon only keeps discounting
            // while it is still active, not expired, and (if global-limited) not
            // exhausted. Otherwise an admin has no way to stop an in-flight discount.
            $couponStillValid = $coupon
                && $coupon->is_recurring
                && (bool) $coupon->is_active
                && (is_null($coupon->expires_at) || $coupon->expires_at->isFuture())
                && (is_null($coupon->max_uses) || (int) $coupon->used_count < (int) $coupon->max_uses);

            if ($couponStillValid) {
                $discountAmount = $coupon->type === 'percent'
                    ? ($amount * ($coupon->value / 100))
                    : $coupon->value;

                if ($coupon->max_discount && $discountAmount > $coupon->max_discount) {
                    $discountAmount = $coupon->max_discount;
                }

                $finalAmount = max(0, $amount - $discountAmount);
                $renewalMetadata['coupon_code'] = $coupon->code;
                $renewalMetadata['discount_applied'] = $discountAmount;
            }
        }

        $renewalPayment = DB::transaction(function () use ($subscription, $gateway, $gatewayPaymentId, $currency, $finalAmount, $periodEnd, $renewalMetadata) {
            // Concurrency-safe idempotency: lock the subscription and re-check for an
            // already-completed payment with this gateway_payment_id inside the lock,
            // so two renewal deliveries can't both create a period + grant credits.
            GatewaySubscription::whereKey($subscription->id)->lockForUpdate()->first();

            $alreadyProcessed = Payment::where('gateway', $gateway)
                ->where('gateway_payment_id', $gatewayPaymentId)
                ->where('status', 'completed')
                ->exists();

            if ($alreadyProcessed) {
                return null;
            }

            $subscription->update([
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
                'amount' => $finalAmount,
                'currency' => $currency,
            ]);

            $renewalPayment = Payment::create([
                'user_id' => $subscription->user_id,
                'plan_id' => $subscription->plan_id,
                'subscription_id' => $subscription->id,
                'gateway' => $gateway,
                'gateway_payment_id' => $gatewayPaymentId,
                'amount' => $finalAmount,
                'currency' => $currency,
                'status' => 'completed',
                'type' => 'subscription',
                'metadata' => $renewalMetadata,
            ]);

            $subscription->user()->update([
                'subscription_status' => 'active',
                'subscription_ends_at' => $periodEnd,
            ]);

            // Refresh the plan's credit allowance for the renewed period.
            if (($renewUser = $subscription->user) && ($renewPlan = Plan::find($subscription->plan_id))) {
                $renewUser->grantPlanAllowance((float) $renewPlan->credits, "Plan renewal credits: {$renewPlan->name}");
            }

            return $renewalPayment;
        });

        // Idempotent redelivery — nothing new was processed.
        if (! $renewalPayment) {
            return $subscription;
        }

        $this->awardAffiliateRewards($renewalPayment);

        app(NotificationEventService::class)->subscriptionRenewed($subscription);

        return $subscription;
    }

    /**
     * Lifetime plans never expire: their period end is stored as NULL.
     */
    protected function periodEndFor(string $billingCycle): ?\Illuminate\Support\Carbon
    {
        return match ($billingCycle) {
            'lifetime' => null,
            'yearly' => now()->addYear(),
            default => now()->addMonth(),
        };
    }

    /**
     * Credit affiliate commission + first-purchase referral credits for a
     * completed payment. Failures are isolated so a payment that already
     * succeeded is never rolled back by an affiliate-side problem.
     */
    protected function awardAffiliateRewards(Payment $payment): void
    {
        try {
            $affiliate = app(AffiliateService::class);
            $affiliate->createCommissionForPayment($payment);
            $affiliate->awardReferralCreditsForPayment($payment);
        } catch (Throwable $e) {
            Log::warning('Affiliate reward processing failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function cancelAtPeriodEnd(GatewaySubscription $subscription): void
    {
        $subscription->cancel();

        $subscription->user()->update([
            'subscription_status' => 'cancelled',
            'subscription_ends_at' => $subscription->current_period_end,
        ]);

        app(NotificationEventService::class)->subscriptionCanceled($subscription);
    }

    /**
     * Schedule a downgrade to a lower plan that takes effect at the end of the
     * current paid period — no charge, no refund. Downgrading to a free plan is
     * simply a cancel-at-period-end (access until period end, then expire).
     */
    /**
     * Schedule a paid downgrade to apply at period end. Only call this for a
     * subscription that can actually auto-bill the lower plan (an in-place-capable
     * recurring gateway) — the caller decides; a free target or a gateway that
     * cannot plan-swap should use cancelAtPeriodEnd instead.
     */
    public function scheduleDowngrade(GatewaySubscription $subscription, Plan $target, string $billingCycle): void
    {
        $effectiveAt = $subscription->current_period_end ?? now();

        $subscription->update([
            'scheduled_plan_id' => $target->id,
            'scheduled_billing_cycle' => $billingCycle,
            'scheduled_change_at' => $effectiveAt,
        ]);

        app(NotificationEventService::class)->subscriptionDowngradeScheduled($subscription, $target, $effectiveAt);
    }

    public function cancelScheduledDowngrade(GatewaySubscription $subscription): void
    {
        $subscription->update([
            'scheduled_plan_id' => null,
            'scheduled_billing_cycle' => null,
            'scheduled_change_at' => null,
        ]);
    }

    /**
     * Apply any scheduled downgrades whose effective date has passed: switch the
     * subscription + user to the target plan and start a fresh period. Runs on
     * the hourly cron BEFORE expirePastDue so a scheduled change is not mistaken
     * for an expiry.
     */
    public function applyScheduledChanges(): int
    {
        $count = 0;

        GatewaySubscription::query()
            ->whereNotNull('scheduled_plan_id')
            ->whereNotNull('scheduled_change_at')
            ->where('scheduled_change_at', '<=', now())
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->with(['user', 'plan', 'scheduledPlan'])
            ->chunkById(100, function ($subscriptions) use (&$count) {
                foreach ($subscriptions as $subscription) {
                    $target = $subscription->scheduledPlan;

                    // Orphaned schedule (target plan deleted or user gone) — clear it.
                    if (! $target || ! $subscription->user) {
                        $this->cancelScheduledDowngrade($subscription);

                        continue;
                    }

                    $cycle = $subscription->scheduled_billing_cycle ?: ($subscription->billing_cycle ?? 'monthly');
                    $periodEnd = $this->periodEndFor($cycle);
                    $oldPlan = $subscription->plan;

                    $subscription->update([
                        'plan_id' => $target->id,
                        'billing_cycle' => $cycle,
                        'status' => 'active',
                        'amount' => $this->planCycleAmount($target, $cycle),
                        'current_period_start' => now(),
                        'current_period_end' => $periodEnd,
                        'cancelled_at' => null,
                        'scheduled_plan_id' => null,
                        'scheduled_billing_cycle' => null,
                        'scheduled_change_at' => null,
                    ]);

                    $subscription->user()->update([
                        'plan_id' => $target->id,
                        'subscription_status' => 'active',
                        'subscription_ends_at' => $periodEnd,
                    ]);

                    // Email confirmation already went out when the downgrade was
                    // scheduled — only an in-app note here.
                    app(NotificationEventService::class)->subscriptionDowngradeApplied($subscription, $oldPlan);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Nominal list price of a plan for a billing cycle. Used as the recorded
     * amount when a scheduled downgrade is applied (no charge occurs).
     */
    protected function planCycleAmount(Plan $plan, string $cycle): float
    {
        return (float) match ($cycle) {
            'yearly' => $plan->price_yearly,
            'lifetime' => $plan->price_lifetime,
            default => $plan->price_monthly,
        };
    }

    /**
     * Expire any other active/trialing subscriptions for the user when a new one
     * supersedes them (upgrade checkout). Does NOT notify or downgrade access —
     * the user is moving up, not losing anything.
     */
    protected function supersedePriorSubscriptions(int $userId, int $keepSubscriptionId): void
    {
        GatewaySubscription::query()
            ->where('user_id', $userId)
            ->where('id', '!=', $keepSubscriptionId)
            ->whereIn('status', [GatewaySubscription::STATUS_ACTIVE, GatewaySubscription::STATUS_TRIALING])
            ->update([
                'status' => 'expired',
                'current_period_end' => now(),
                'scheduled_plan_id' => null,
                'scheduled_billing_cycle' => null,
                'scheduled_change_at' => null,
            ]);
    }

    /**
     * End a subscription immediately (gateway-side termination, refunds,
     * admin immediate deactivation) and revoke plan access.
     */
    public function expireNow(GatewaySubscription $subscription): void
    {
        $subscription->update([
            'status' => 'expired',
            'current_period_end' => now(),
        ]);

        $this->downgradeUserIfNoOtherAccess($subscription);

        app(NotificationEventService::class)->subscriptionExpired($subscription);
    }

    public function resume(GatewaySubscription $subscription): void
    {
        $subscription->update([
            'status' => 'active',
            'cancelled_at' => null,
        ]);

        $subscription->user()->update([
            'plan_id' => $subscription->plan_id,
            'subscription_status' => 'active',
            'subscription_ends_at' => $subscription->current_period_end,
        ]);
    }

    public function fail(Payment $payment, string $reason): void
    {
        // A late failure webhook must never clobber a payment that already completed.
        if ($payment->status === 'completed') {
            return;
        }

        $this->releaseCouponReservations($payment);
        $payment->update(['status' => 'failed']);

        app(NotificationEventService::class)->paymentFailed($payment, $reason);
    }

    /**
     * Return coupon slots reserved at checkout (global + per-user cache key) when a
     * payment fails before completing, so the coupon isn't permanently consumed by an
     * abandoned/failed checkout.
     */
    protected function releaseCouponReservations(Payment $payment): void
    {
        $metadata = $payment->metadata ?: [];

        if (! empty($metadata['coupon_global_reserved']) && ! empty($metadata['coupon_code'])) {
            \App\Models\Coupon::where('code', $metadata['coupon_code'])->first()?->releaseGlobalUse();
        }

        \App\Models\Coupon::releaseReservation($metadata['coupon_reservation_key'] ?? null);
    }

    /**
     * Manual rejection by an administrator — unlike fail(), the user is told the
     * payment was reviewed and rejected (with the reason), not that billing failed.
     */
    public function rejectPayment(Payment $payment, ?string $reason = null): void
    {
        if ($payment->status === 'completed') {
            return;
        }

        $this->releaseCouponReservations($payment);
        $payment->update(['status' => 'failed']);

        app(NotificationEventService::class)->paymentRejected($payment, $reason);
    }

    public function refund(Payment $payment, string $gatewayReference = ''): void
    {
        if ($payment->status === 'refunded') {
            return;
        }

        $metadata = $payment->metadata ?: [];
        $metadata['refund'] = [
            'gateway_reference' => $gatewayReference,
            'refunded_at' => now()->toISOString(),
        ];

        $payment->update(['status' => 'refunded', 'metadata' => $metadata]);

        // Claw back any affiliate commission credited for this payment so the
        // referrer does not keep rewards from a reversed order.
        try {
            app(AffiliateService::class)->clawbackCommissionForPayment($payment);
        } catch (Throwable $e) {
            Log::warning('Affiliate commission clawback failed on refund', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        // A refunded subscription payment revokes the paid period immediately.
        if ($payment->type === 'subscription' && $payment->subscription) {
            $subscription = $payment->subscription;
            $subscription->update([
                'status' => 'expired',
                'current_period_end' => now(),
            ]);

            $this->downgradeUserIfNoOtherAccess($subscription);
        }
    }

    /**
     * Expire abandoned checkout payments: gateway sessions the buyer started but
     * never completed. These accumulate as stale 'pending' rows and hold a coupon's
     * reserved slot indefinitely, so we fail them and release their reservations.
     *
     * Bank transfers are EXCLUDED — they legitimately stay pending until an admin
     * reviews the proof. The threshold is comfortably beyond any hosted-gateway
     * session lifetime (Stripe/etc. sessions expire at the gateway within ~24h), so
     * a payment this old can no longer complete. A row lock + re-check avoids racing
     * a late completion webhook.
     *
     * @return int payments expired
     */
    public function expireAbandonedCheckouts(int $olderThanHours = 24): int
    {
        $cutoff = now()->subHours(max(1, $olderThanHours));

        $stale = Payment::where('status', 'pending')
            ->where('gateway', '!=', 'bank_transfer')
            ->where('created_at', '<', $cutoff)
            ->get();

        $count = 0;

        foreach ($stale as $payment) {
            DB::transaction(function () use ($payment, &$count) {
                $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
                if (! $locked || $locked->status !== 'pending') {
                    return; // completed/failed between the scan and the lock
                }

                $this->releaseCouponReservations($locked);
                $locked->update([
                    'status' => 'failed',
                    'metadata' => array_merge($locked->metadata ?: [], ['expired_reason' => 'abandoned_checkout']),
                ]);
                $count++;
            });
        }

        return $count;
    }

    /**
     * Remove the user's plan unless another still-valid subscription grants access
     * (e.g. the user re-subscribed to a different plan before the old one lapsed).
     */
    protected function downgradeUserIfNoOtherAccess(GatewaySubscription $subscription): void
    {
        $user = $subscription->user;

        if (! $user) {
            return;
        }

        // "Other access" includes a subscription cancelled but still within its paid
        // grace period (isWithinGracePeriod) — the user paid through current_period_end
        // and must not lose access early because an unrelated sub was refunded/expired.
        $hasOtherAccess = GatewaySubscription::where('user_id', $user->id)
            ->whereKeyNot($subscription->id)
            ->whereIn('status', ['active', 'trialing', 'cancelled'])
            ->where(function ($query) {
                $query->whereNull('current_period_end')->orWhere('current_period_end', '>', now());
            })
            ->exists();

        if ($hasOtherAccess) {
            return;
        }

        $user->update([
            'plan_id' => null,
            'subscription_status' => 'none',
            'subscription_ends_at' => now(),
        ]);
    }

    public function expirePastDue(): int
    {
        $count = 0;

        // Covers running subscriptions past their period end, ended trials, failed
        // renewals (past_due), and cancelled subscriptions whose paid grace period
        // has lapsed. Lifetime subscriptions have a NULL period end and never match.
        // Subs with a pending scheduled downgrade are skipped here — they are
        // handled by applyScheduledChanges (order-independent guarantee).
        GatewaySubscription::query()
            ->whereIn('status', ['active', 'trialing', 'past_due', 'cancelled', 'canceled'])
            ->whereNull('scheduled_plan_id')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->with('user')
            ->chunkById(100, function ($subscriptions) use (&$count) {
                foreach ($subscriptions as $subscription) {
                    // A lapsing trial is a distinct event from a paid subscription
                    // expiring — notify with the trial-ended email in that case.
                    $wasTrialing = $subscription->status === GatewaySubscription::STATUS_TRIALING;

                    $subscription->update(['status' => 'expired']);

                    $this->downgradeUserIfNoOtherAccess($subscription);

                    if ($wasTrialing) {
                        app(NotificationEventService::class)->trialEnded($subscription);
                    } else {
                        app(NotificationEventService::class)->subscriptionExpired($subscription);
                    }
                    $count++;
                }
            });

        return $count;
    }
}
