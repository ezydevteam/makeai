<?php

namespace App\Services\Subscription;

use App\Models\Coupon;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionLifecycleService
{
    public function activateFromPayment(Payment $payment, ?string $gatewayPaymentId = null, ?string $gatewaySubscriptionId = null): Payment
    {
        return DB::transaction(function () use ($payment, $gatewayPaymentId, $gatewaySubscriptionId) {
            $payment = Payment::query()
                ->with(['plan', 'user'])
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === 'completed') {
                return $payment;
            }

            $metadata = $payment->metadata ?: [];
            $billing = (string) ($metadata['billing_cycle'] ?? 'monthly');
            $periodEnd = $this->periodEndFor($billing);

            $subscription = Subscription::create([
                'user_id' => $payment->user_id,
                'plan_id' => $payment->plan_id,
                'billing_cycle' => $billing,
                'status' => 'active',
                'gateway' => $payment->gateway,
                'gateway_subscription_id' => $gatewaySubscriptionId,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
            ]);

            $payment->update([
                'subscription_id' => $subscription->id,
                'gateway_payment_id' => $gatewayPaymentId ?: $payment->gateway_payment_id,
                'status' => 'completed',
                'metadata' => [
                    ...$metadata,
                    'activated_at' => now()->toISOString(),
                ],
            ]);

            $this->markUserActive($payment->user, $payment->plan, $periodEnd);
            $this->allocatePlanCredits($payment->user, $payment->plan, $payment->ulid);
            $this->markCouponUsed($metadata);
            $this->applyFirstPurchaseReferral($payment);

            return $payment->fresh(['plan', 'user', 'subscription']);
        });
    }

    public function renewFromGatewaySubscription(string $gateway, string $gatewaySubscriptionId, ?string $gatewayPaymentId = null, ?float $amount = null, ?string $currency = null): ?Payment
    {
        return DB::transaction(function () use ($gateway, $gatewaySubscriptionId, $gatewayPaymentId, $amount, $currency) {
            $subscription = Subscription::query()
                ->with(['plan', 'user'])
                ->where('gateway', $gateway)
                ->where('gateway_subscription_id', $gatewaySubscriptionId)
                ->lockForUpdate()
                ->first();

            if (! $subscription) {
                return null;
            }

            if ($gatewayPaymentId && Payment::where('gateway_payment_id', $gatewayPaymentId)->exists()) {
                return Payment::where('gateway_payment_id', $gatewayPaymentId)->first();
            }

            $periodEnd = $this->periodEndFor($subscription->billing_cycle);
            $payment = Payment::create([
                'user_id' => $subscription->user_id,
                'plan_id' => $subscription->plan_id,
                'subscription_id' => $subscription->id,
                'gateway' => $gateway,
                'gateway_payment_id' => $gatewayPaymentId,
                'amount' => $amount ?? (float) $subscription->amount,
                'currency' => $currency ?? $subscription->currency,
                'status' => 'completed',
                'type' => 'subscription',
                'metadata' => [
                    'billing_cycle' => $subscription->billing_cycle,
                    'renewed_at' => now()->toISOString(),
                    'created_from' => 'gateway_renewal_webhook',
                ],
            ]);

            $subscription->update([
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
                'cancelled_at' => null,
            ]);

            $this->markUserActive($subscription->user, $subscription->plan, $periodEnd);
            $this->allocatePlanCredits($subscription->user, $subscription->plan, $payment->ulid);

            return $payment;
        });
    }

    public function cancelAtPeriodEnd(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription) {
            $subscription = Subscription::query()
                ->with('user')
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->firstOrFail();

            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            $subscription->user->update([
                'subscription_status' => 'canceled',
                'subscription_ends_at' => $subscription->current_period_end,
            ]);

            return $subscription->fresh();
        });
    }

    public function expirePastDue(): int
    {
        $expired = 0;

        Subscription::query()
            ->with('user')
            ->whereIn('status', ['active', 'trialing', 'cancelled'])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->chunkById(100, function ($subscriptions) use (&$expired) {
                foreach ($subscriptions as $subscription) {
                    $subscription->update(['status' => 'expired']);
                    $subscription->user->update([
                        'subscription_status' => 'none',
                        'subscription_ends_at' => $subscription->current_period_end,
                    ]);
                    $expired++;
                }
            });

        return $expired;
    }

    public function fail(Payment $payment, ?string $reason = null): void
    {
        $metadata = $payment->metadata ?: [];

        if ($reason) {
            $metadata['failure_reason'] = $reason;
        }

        $payment->update([
            'status' => 'failed',
            'metadata' => $metadata,
        ]);
    }

    private function periodEndFor(string $billing): ?Carbon
    {
        return match ($billing) {
            'monthly' => now()->addMonth(),
            'yearly' => now()->addYear(),
            default => null,
        };
    }

    private function markUserActive(User $user, ?Plan $plan, ?Carbon $periodEnd): void
    {
        $user->update([
            'plan_id' => $plan?->id,
            'subscription_status' => 'active',
            'subscription_ends_at' => $periodEnd,
            'trial_ends_at' => null,
        ]);
    }

    private function allocatePlanCredits(User $user, ?Plan $plan, string $paymentUlid): void
    {
        if (! $plan || (float) $plan->credits <= 0) {
            return;
        }

        $user->addCredits(
            (float) $plan->credits,
            'purchase',
            'Subscription credits',
            ['payment_ulid' => $paymentUlid, 'plan_id' => $plan->id]
        );
    }

    private function markCouponUsed(array $metadata): void
    {
        $couponId = data_get($metadata, 'coupon.id');

        if ($couponId) {
            Coupon::whereKey($couponId)->increment('used_count');
        }
    }

    private function applyFirstPurchaseReferral(Payment $payment): void
    {
        $user = $payment->user;

        if (! $user->referred_by) {
            return;
        }

        $alreadyRewarded = Payment::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->where('id', '!=', $payment->id)
            ->exists();

        if ($alreadyRewarded) {
            return;
        }

        $referrer = User::find($user->referred_by);

        if (! $referrer) {
            return;
        }

        $reward = round(((float) $payment->amount * (float) settings('referral_commission_percentage', 10)) / 100, 4);

        if ($reward <= 0) {
            return;
        }

        $referrer->increment('referral_earnings', $reward);
        $referrer->increment('referral_count');
        $referrer->addCredits($reward, 'referral', 'Referral commission', [
            'payment_ulid' => $payment->ulid,
            'referred_user_id' => $user->id,
        ]);
    }
}
