<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Subscription\SubscriptionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function handleWebhook(Request $request, SubscriptionLifecycleService $lifecycle): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature');
        $secret = config('cashier.webhook.secret');

        if ($secret && ! $this->isValidSignature($payload, $signature, $secret)) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);
        $type = $event['type'] ?? '';
        $object = $event['data']['object'] ?? [];

        match ($type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($object, $lifecycle),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($object, $lifecycle),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($object, $lifecycle),
            default => null,
        };

        return response()->json(['success' => true]);
    }

    private function handleCheckoutCompleted(array $session, SubscriptionLifecycleService $lifecycle): void
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

        // Idempotency check
        if ($payment->status === 'completed') {
            return;
        }

        $lifecycle->activateFromPayment($payment, $session['id'], $subscriptionId);
    }

    private function handleSubscriptionUpdated(array $subscription, SubscriptionLifecycleService $lifecycle): void
    {
        $stripeSubscriptionId = $subscription['id'] ?? null;

        if (! $stripeSubscriptionId) {
            return;
        }

        $payment = Payment::where('gateway_payment_id', $stripeSubscriptionId)->first();

        if ($payment) {
            $status = $subscription['status'] ?? 'active';
            $mappedStatus = match ($status) {
                'active', 'trialing' => 'active',
                'past_due' => 'past_due',
                default => 'canceled',
            };

            $payment->subscription?->update(['status' => $mappedStatus]);
        }
    }

    private function handleSubscriptionDeleted(array $subscription, SubscriptionLifecycleService $lifecycle): void
    {
        $stripeSubscriptionId = $subscription['id'] ?? null;

        if (! $stripeSubscriptionId) {
            return;
        }

        $payment = Payment::where('gateway_payment_id', $stripeSubscriptionId)->first();

        if ($payment && $payment->subscription) {
            $lifecycle->cancelAtPeriodEnd($payment->subscription);
        }
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

        $signedPayload = $timestamp.'.'.$payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expected, $signature);
    }
}
