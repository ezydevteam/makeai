<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\GatewaySubscription;
use App\Models\Payment;
use App\Services\Payment\PaymentActivationService;
use App\Services\Subscription\SubscriptionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function handleWebhook(Request $request, SubscriptionLifecycleService $lifecycle, PaymentActivationService $activation): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature');
        $secret = config('cashier.webhook.secret');

        // Signature verification is mandatory: an unverified webhook would let
        // anyone activate subscriptions by posting forged events.
        if (! $secret) {
            Log::warning('Stripe webhook rejected: no webhook secret configured. Set it in Admin → Payment Gateways → Stripe.');

            return response()->json(['error' => 'Webhook secret not configured'], 400);
        }

        if (! $this->isValidSignature($payload, $signature, $secret)) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);
        $type = $event['type'] ?? '';
        $object = $event['data']['object'] ?? [];

        match ($type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($object, $lifecycle, $activation),
            'invoice.paid', 'invoice.payment_succeeded' => $this->handleInvoicePaid($object, $lifecycle),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($object, $lifecycle),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($object, $lifecycle),
            'charge.refunded' => $this->handleChargeRefunded($object, $lifecycle),
            default => null,
        };

        return response()->json(['success' => true]);
    }

    private function handleCheckoutCompleted(array $session, SubscriptionLifecycleService $lifecycle, PaymentActivationService $activation): void
    {
        $paymentId = $session['metadata']['payment_id'] ?? null;
        $subscriptionId = $session['subscription'] ?? null;

        if (! $paymentId) {
            Log::warning('Stripe checkout completed but payment_id missing in metadata.');

            return;
        }

        $payment = Payment::where('ulid', $paymentId)->first();

        if (! $payment) {
            Log::warning('Stripe checkout completed but payment not found.', ['payment_id' => $paymentId]);

            return;
        }

        if ($payment->status === 'completed') {
            return;
        }

        if ($payment->type === 'credit_topup') {
            $activation->activateCreditTopup($payment, $session['id']);
        } else {
            $lifecycle->activateFromPayment($payment, $session['id'], $subscriptionId);
        }
    }

    /**
     * Recurring charges: extend the local period so the customer keeps access.
     * The first invoice (billing_reason=subscription_create) is already handled
     * by checkout.session.completed — processing it here would double-book it.
     */
    private function handleInvoicePaid(array $invoice, SubscriptionLifecycleService $lifecycle): void
    {
        if (($invoice['billing_reason'] ?? '') !== 'subscription_cycle') {
            return;
        }

        $stripeSubscriptionId = $invoice['subscription'] ?? null;

        if (! $stripeSubscriptionId) {
            return;
        }

        $lifecycle->renewFromGatewaySubscription(
            'stripe',
            (string) $stripeSubscriptionId,
            (string) ($invoice['id'] ?? ''),
            $this->majorAmount((int) ($invoice['amount_paid'] ?? 0), (string) ($invoice['currency'] ?? 'usd')),
            strtoupper((string) ($invoice['currency'] ?? 'USD'))
        );
    }

    private function handleInvoicePaymentFailed(array $invoice): void
    {
        $stripeSubscriptionId = $invoice['subscription'] ?? null;

        if (! $stripeSubscriptionId) {
            return;
        }

        GatewaySubscription::where('gateway', 'stripe')
            ->where('gateway_subscription_id', $stripeSubscriptionId)
            ->whereIn('status', [GatewaySubscription::STATUS_ACTIVE, GatewaySubscription::STATUS_TRIALING])
            ->update(['status' => GatewaySubscription::STATUS_PAST_DUE]);
    }

    private function handleSubscriptionUpdated(array $stripeSubscription, SubscriptionLifecycleService $lifecycle): void
    {
        $subscription = $this->findSubscription($stripeSubscription);

        if (! $subscription) {
            return;
        }

        $periodEnd = isset($stripeSubscription['current_period_end'])
            ? Carbon::createFromTimestamp($stripeSubscription['current_period_end'])
            : $subscription->current_period_end;

        $stripeStatus = (string) ($stripeSubscription['status'] ?? 'active');

        if (in_array($stripeStatus, ['canceled', 'unpaid', 'incomplete_expired'], true)) {
            $lifecycle->expireNow($subscription);

            return;
        }

        if (! empty($stripeSubscription['cancel_at_period_end'])) {
            $subscription->update(['current_period_end' => $periodEnd]);

            if (! $subscription->isCancelled()) {
                $lifecycle->cancelAtPeriodEnd($subscription->fresh());
            }

            return;
        }

        $mapped = match ($stripeStatus) {
            'trialing' => GatewaySubscription::STATUS_TRIALING,
            'past_due' => GatewaySubscription::STATUS_PAST_DUE,
            default => GatewaySubscription::STATUS_ACTIVE,
        };

        $subscription->update([
            'status' => $mapped,
            'current_period_end' => $periodEnd,
            'cancelled_at' => null,
        ]);

        if (in_array($mapped, [GatewaySubscription::STATUS_ACTIVE, GatewaySubscription::STATUS_TRIALING], true)) {
            $subscription->user()->update([
                'plan_id' => $subscription->plan_id,
                'subscription_status' => $mapped,
                'subscription_ends_at' => $periodEnd,
            ]);
        }
    }

    private function handleSubscriptionDeleted(array $stripeSubscription, SubscriptionLifecycleService $lifecycle): void
    {
        $subscription = $this->findSubscription($stripeSubscription);

        if ($subscription && $subscription->status !== GatewaySubscription::STATUS_EXPIRED) {
            $lifecycle->expireNow($subscription);
        }
    }

    private function handleChargeRefunded(array $charge, SubscriptionLifecycleService $lifecycle): void
    {
        $paymentIntent = $charge['payment_intent'] ?? null;
        $paymentUlid = $charge['metadata']['payment_id'] ?? null;

        $payment = Payment::query()
            ->where('gateway', 'stripe')
            ->when($paymentUlid, fn ($q) => $q->where('ulid', $paymentUlid))
            ->when(! $paymentUlid && $paymentIntent, fn ($q) => $q->where('gateway_payment_id', $paymentIntent))
            ->first();

        if ($payment) {
            $lifecycle->refund($payment, (string) ($charge['id'] ?? ''));
        }
    }

    private function findSubscription(array $stripeSubscription): ?GatewaySubscription
    {
        $stripeSubscriptionId = $stripeSubscription['id'] ?? null;

        if (! $stripeSubscriptionId) {
            return null;
        }

        return GatewaySubscription::where('gateway', 'stripe')
            ->where('gateway_subscription_id', $stripeSubscriptionId)
            ->first();
    }

    private function majorAmount(int $minorAmount, string $currency): float
    {
        $zeroDecimal = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

        return in_array(strtoupper($currency), $zeroDecimal, true)
            ? (float) $minorAmount
            : round($minorAmount / 100, 2);
    }

    private function isValidSignature(string $payload, string $signatureHeader, string $secret): bool
    {
        if (! $signatureHeader) {
            return false;
        }

        $parts = collect(explode(',', $signatureHeader))
            ->mapWithKeys(function (string $part) {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, null);

                return [$key => $value];
            });

        $timestamp = $parts->get('t');
        $signature = $parts->get('v1');

        if (! $timestamp || ! $signature) {
            return false;
        }

        $tolerance = (int) config('cashier.webhook.tolerance', 300);

        if ($tolerance > 0 && abs(now()->getTimestamp() - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return hash_equals($expected, $signature);
    }
}
