<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function handleWebhook(Request $request): JsonResponse
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
            'checkout.session.completed' => $this->handleCheckoutCompleted($object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($object),
            default => null,
        };

        return response()->json(['success' => true]);
    }

    private function handleCheckoutCompleted(array $session): void
    {
        $metadata = $session['metadata'] ?? [];
        $planSlug = $metadata['plan_slug'] ?? null;
        $billingCycle = $metadata['billing_cycle'] ?? 'monthly';
        $customerEmail = $session['customer_details']['email'] ?? $session['customer_email'] ?? null;

        if (! $planSlug || ! $customerEmail) {
            return;
        }

        $plan = Plan::where('slug', $planSlug)->first();
        $user = User::where('email', $customerEmail)->first();

        if (! $user || ! $plan) {
            Log::warning('Stripe checkout completed but user or plan not found.', [
                'email' => $customerEmail,
                'plan_slug' => $planSlug,
            ]);

            return;
        }

        $user->update([
            'plan_id' => $plan->id,
            'subscription_status' => 'active',
            'subscription_ends_at' => $billingCycle === 'monthly' ? now()->addMonth() : now()->addYear(),
        ]);

        Log::info('Stripe checkout activated.', [
            'user_id' => $user->id,
            'plan_slug' => $planSlug,
        ]);
    }

    private function handleSubscriptionUpdated(array $subscription): void
    {
        $stripeCustomerId = $subscription['customer'] ?? null;

        if (! $stripeCustomerId) {
            return;
        }

        $user = User::where('stripe_id', $stripeCustomerId)->first();

        if (! $user) {
            return;
        }

        $status = $subscription['status'] ?? 'active';

        $user->update([
            'subscription_status' => match ($status) {
                'active', 'trialing' => 'active',
                'past_due' => 'past_due',
                default => 'canceled',
            },
        ]);
    }

    private function handleSubscriptionDeleted(array $subscription): void
    {
        $stripeCustomerId = $subscription['customer'] ?? null;

        if (! $stripeCustomerId) {
            return;
        }

        $user = User::where('stripe_id', $stripeCustomerId)->first();

        if ($user) {
            $user->update([
                'subscription_status' => 'canceled',
            ]);
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
