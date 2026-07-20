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
            // async_payment_succeeded is the settle event for delayed-notification methods
            // whose session completed as `unpaid` — the same fulfilment path, once paid.
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $this->handleCheckoutCompleted($object, $lifecycle, $activation),
            'checkout.session.async_payment_failed' => $this->handleCheckoutAsyncFailed($object, $lifecycle),
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
        $subscriptionId = $this->idOf($session['subscription'] ?? null);

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

        // `checkout.session.completed` does NOT mean the money arrived. Delayed-notification
        // methods (bank debits, vouchers) complete the session with payment_status=unpaid
        // and settle days later via checkout.session.async_payment_succeeded — or fail.
        // Granting the plan here would hand out access before being paid.
        $paymentStatus = (string) ($session['payment_status'] ?? 'paid');

        if (! in_array($paymentStatus, ['paid', 'no_payment_required'], true)) {
            Log::info('Stripe checkout session is not paid yet — deferring activation.', [
                'payment_ulid' => $payment->ulid,
                'payment_status' => $paymentStatus,
            ]);

            return;
        }

        // Persist the Stripe objects that a later refund webhook can be matched against.
        // A charge.refunded event carries only the charge (and its payment_intent), never
        // our checkout session id — so without these there is nothing to reconcile.
        $this->rememberStripeReferences($payment, [
            'stripe_payment_intent' => $this->idOf($session['payment_intent'] ?? null),
            'stripe_invoice' => $this->idOf($session['invoice'] ?? null),
        ]);

        if ($payment->type === 'credit_topup') {
            $activation->activateCreditTopup($payment, $session['id']);
        } else {
            $lifecycle->activateFromPayment($payment, $session['id'], $subscriptionId);
        }
    }

    /**
     * A delayed-notification payment that ultimately failed — release the pending payment
     * (and any coupon slot it was holding) instead of leaving it pending forever.
     */
    private function handleCheckoutAsyncFailed(array $session, SubscriptionLifecycleService $lifecycle): void
    {
        $paymentId = $session['metadata']['payment_id'] ?? null;
        $payment = $paymentId ? Payment::where('ulid', $paymentId)->first() : null;

        if ($payment) {
            $lifecycle->fail($payment, 'stripe.checkout.async_payment_failed');
        }
    }

    /**
     * Store gateway-side ids on the payment without disturbing its status.
     */
    private function rememberStripeReferences(Payment $payment, array $references): void
    {
        $references = array_filter($references, fn ($value) => filled($value));

        if ($references === []) {
            return;
        }

        $payment->update(['metadata' => array_merge($payment->metadata ?: [], $references)]);
    }

    /**
     * Stripe fields are either a bare id or an expanded object — normalise to the id.
     */
    private function idOf(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['id'] ?? null;
        }

        return filled($value) ? (string) $value : null;
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

        $stripeSubscriptionId = $this->invoiceSubscriptionId($invoice);

        if (! $stripeSubscriptionId) {
            return;
        }

        $lifecycle->renewFromGatewaySubscription(
            'stripe',
            $stripeSubscriptionId,
            (string) ($invoice['id'] ?? ''),
            $this->majorAmount((int) ($invoice['amount_paid'] ?? 0), (string) ($invoice['currency'] ?? 'usd')),
            strtoupper((string) ($invoice['currency'] ?? 'USD')),
            ['stripe_payment_intent' => $this->invoicePaymentIntentId($invoice)],
        );
    }

    /**
     * The PaymentIntent that settled an invoice, used to match a later charge.refunded
     * back to this renewal. "basil" removed invoice.payment_intent and surfaces it via
     * the invoice's payments collection, which is only present when Stripe includes that
     * list in the payload — so this is best-effort, and the refund handler logs loudly
     * when it cannot match rather than silently doing nothing.
     */
    private function invoicePaymentIntentId(array $invoice): ?string
    {
        return $this->idOf(
            $invoice['payment_intent']
                ?? data_get($invoice, 'payments.data.0.payment.payment_intent')
        );
    }

    private function handleInvoicePaymentFailed(array $invoice): void
    {
        $stripeSubscriptionId = $this->invoiceSubscriptionId($invoice);

        if (! $stripeSubscriptionId) {
            return;
        }

        GatewaySubscription::where('gateway', 'stripe')
            ->where('gateway_subscription_id', $stripeSubscriptionId)
            ->whereIn('status', [GatewaySubscription::STATUS_ACTIVE, GatewaySubscription::STATUS_TRIALING])
            ->update(['status' => GatewaySubscription::STATUS_PAST_DUE]);
    }

    /**
     * The subscription id an invoice belongs to.
     *
     * Stripe's 2025 "basil" API — the version this SDK and Cashier pin — REMOVED
     * `invoice.subscription` and moved it under `invoice.parent`. A webhook endpoint
     * pinned to that version therefore delivers renewals with no `subscription` field,
     * and reading only the legacy key meant every renewal was silently skipped: Stripe
     * charged the card, the local period was never extended, and the expiry cron then
     * downgraded a paying customer. Read both shapes so the handler is correct whatever
     * API version the merchant's endpoint is pinned to.
     */
    private function invoiceSubscriptionId(array $invoice): ?string
    {
        $subscription = $invoice['subscription']
            ?? data_get($invoice, 'parent.subscription_details.subscription');

        // Either shape may carry an expanded object instead of a bare id.
        if (is_array($subscription)) {
            $subscription = $subscription['id'] ?? null;
        }

        return filled($subscription) ? (string) $subscription : null;
    }

    /**
     * A Stripe subscription's current period end. "basil" also removed the top-level
     * `current_period_end` and moved it onto each subscription item.
     */
    private function subscriptionPeriodEnd(array $stripeSubscription): ?Carbon
    {
        $timestamp = $stripeSubscription['current_period_end']
            ?? data_get($stripeSubscription, 'items.data.0.current_period_end');

        return is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }

    private function handleSubscriptionUpdated(array $stripeSubscription, SubscriptionLifecycleService $lifecycle): void
    {
        $subscription = $this->findSubscription($stripeSubscription);

        if (! $subscription) {
            return;
        }

        $periodEnd = $this->subscriptionPeriodEnd($stripeSubscription) ?? $subscription->current_period_end;

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
        $payment = $this->findChargedPayment($charge);

        if (! $payment) {
            // Loud rather than silent: an unreconciled refund means a customer was given
            // their money back but still holds the plan.
            Log::warning('Stripe charge.refunded could not be matched to a payment.', [
                'charge_id' => $charge['id'] ?? null,
                'payment_intent' => $this->idOf($charge['payment_intent'] ?? null),
            ]);

            return;
        }

        // charge.refunded fires for PARTIAL refunds too. Only a full refund revokes the
        // paid period — a partial one is recorded, and access is left intact.
        $amount = (int) ($charge['amount'] ?? 0);
        $amountRefunded = (int) ($charge['amount_refunded'] ?? 0);

        if ($amount > 0 && $amountRefunded > 0 && $amountRefunded < $amount) {
            $currency = (string) ($charge['currency'] ?? 'usd');

            $this->rememberStripeReferences($payment, [
                'partial_refund' => [
                    'charge_id' => (string) ($charge['id'] ?? ''),
                    'amount_refunded' => $this->majorAmount($amountRefunded, $currency),
                    'currency' => strtoupper($currency),
                    'refunded_at' => now()->toISOString(),
                ],
            ]);

            return;
        }

        $lifecycle->refund($payment, (string) ($charge['id'] ?? ''));
    }

    /**
     * Resolve the local payment a Stripe charge belongs to.
     *
     * A charge does not carry our checkout session id — which is what activation stores
     * in gateway_payment_id — so matching on that alone never hit, and refunds were never
     * reconciled at all. We now stamp the payment ulid onto the PaymentIntent at checkout
     * (so it reaches the charge's metadata) and record the intent/invoice id on the
     * payment; either link resolves the charge.
     */
    private function findChargedPayment(array $charge): ?Payment
    {
        $ulid = $charge['metadata']['payment_id'] ?? null;

        if (filled($ulid)) {
            $payment = Payment::where('gateway', 'stripe')->where('ulid', $ulid)->first();

            if ($payment) {
                return $payment;
            }
        }

        $paymentIntent = $this->idOf($charge['payment_intent'] ?? null);

        if (! filled($paymentIntent)) {
            return null;
        }

        return Payment::query()
            ->where('gateway', 'stripe')
            ->where(fn ($query) => $query
                ->where('metadata->stripe_payment_intent', $paymentIntent)
                // Legacy rows stored the intent directly as the gateway payment id.
                ->orWhere('gateway_payment_id', $paymentIntent))
            ->first();
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
