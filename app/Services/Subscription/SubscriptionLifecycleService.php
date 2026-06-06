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

        $subscription->update([
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => $periodEnd,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        Payment::create([
            'user_id' => $subscription->user_id,
            'plan_id' => $subscription->plan_id,
            'subscription_id' => $subscription->id,
            'gateway' => $gateway,
            'gateway_payment_id' => $gatewayPaymentId,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'completed',
            'type' => 'subscription',
            'metadata' => [
                'billing_cycle' => $billingCycle,
                'created_from' => 'gateway_renewal_webhook',
            ],
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
