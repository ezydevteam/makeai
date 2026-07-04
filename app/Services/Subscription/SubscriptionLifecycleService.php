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
        $subscription = DB::transaction(function () use ($payment, $gatewayPaymentId, $gatewaySubscriptionId) {
            // Idempotency check
            if ($payment->status === 'completed') {
                return $payment->subscription ?? GatewaySubscription::where('user_id', $payment->user_id)->latest()->first();
            }

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

            // Atomic coupon usage increment
            if (! empty($metadata['coupon_code'])) {
                $coupon = \App\Models\Coupon::where('code', $metadata['coupon_code'])->first();
                if ($coupon) {
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

            app(NotificationEventService::class)->subscriptionConfirmed($payment->user, $subscription);
            app(NotificationEventService::class)->paymentSuccessful($payment);

            return $subscription;
        });

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
        return DB::transaction(function () use ($user, $plan, $billingCycle, $gatewaySlug, $trialDays, $currencyCode) {
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

            return $subscription;
        });
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

        // Idempotency: gateways redeliver webhooks — never process the same charge twice.
        $alreadyProcessed = Payment::where('gateway', $gateway)
            ->where('gateway_payment_id', $gatewayPaymentId)
            ->where('status', 'completed')
            ->exists();

        if ($alreadyProcessed) {
            return $subscription;
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
            
            if ($coupon && $coupon->is_recurring) {
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

        $this->awardAffiliateRewards($renewalPayment);

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

        $payment->update(['status' => 'failed']);

        app(NotificationEventService::class)->paymentFailed($payment, $reason);
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
     * Remove the user's plan unless another still-valid subscription grants access
     * (e.g. the user re-subscribed to a different plan before the old one lapsed).
     */
    protected function downgradeUserIfNoOtherAccess(GatewaySubscription $subscription): void
    {
        $user = $subscription->user;

        if (! $user) {
            return;
        }

        $hasOtherAccess = GatewaySubscription::where('user_id', $user->id)
            ->whereKeyNot($subscription->id)
            ->whereIn('status', ['active', 'trialing'])
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
        GatewaySubscription::query()
            ->whereIn('status', ['active', 'trialing', 'past_due', 'cancelled', 'canceled'])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->with('user')
            ->chunkById(100, function ($subscriptions) use (&$count) {
                foreach ($subscriptions as $subscription) {
                    $subscription->update(['status' => 'expired']);

                    $this->downgradeUserIfNoOtherAccess($subscription);

                    app(NotificationEventService::class)->subscriptionExpired($subscription);
                    $count++;
                }
            });

        return $count;
    }
}
