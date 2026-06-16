<?php

namespace App\Services\Subscription;

use App\Models\GatewaySubscription;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\NotificationEventService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionLifecycleService
{
    public function activateFromPayment(Payment $payment, string $gatewayPaymentId, ?string $gatewaySubscriptionId = null): GatewaySubscription
    {
        return DB::transaction(function () use ($payment, $gatewayPaymentId, $gatewaySubscriptionId) {
            // Idempotency check
            if ($payment->status === 'completed') {
                return $payment->subscription ?? GatewaySubscription::where('user_id', $payment->user_id)->latest()->first();
            }

            $metadata = $payment->metadata ?: [];
            $billingCycle = $metadata['billing_cycle'] ?? 'monthly';
            $periodEnd = $billingCycle === 'monthly' ? now()->addMonth() : now()->addYear();

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
        $periodEnd = $billingCycle === 'monthly' ? now()->addMonth() : now()->addYear();

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

        Payment::create([
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

        return $subscription;
    }

    public function cancelAtPeriodEnd(GatewaySubscription $subscription): void
    {
        $subscription->cancel();

        $subscription->user()->update([
            'subscription_status' => 'canceled',
            'subscription_ends_at' => $subscription->current_period_end,
        ]);

        app(NotificationEventService::class)->subscriptionCanceled($subscription);
    }

    public function fail(Payment $payment, string $reason): void
    {
        $payment->update(['status' => 'failed']);

        app(NotificationEventService::class)->paymentFailed($payment, $reason);
    }

    public function expirePastDue(): int
    {
        $count = 0;

        GatewaySubscription::query()
            ->where('status', 'active')
            ->where('current_period_end', '<', now())
            ->chunkById(100, function ($subscriptions) use (&$count) {
                foreach ($subscriptions as $subscription) {
                    $subscription->update(['status' => 'expired']);

                    $subscription->user()->update([
                        'subscription_status' => 'none',
                        'subscription_ends_at' => $subscription->current_period_end,
                    ]);

                    app(NotificationEventService::class)->subscriptionExpired($subscription);
                    $count++;
                }
            });

        return $count;
    }
}
