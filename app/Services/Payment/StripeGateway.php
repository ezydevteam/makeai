<?php

namespace App\Services\Payment;

use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Payment Gateway — implements PaymentGatewayInterface.
 * Ref: AI_SaaS_Master_Prompt Part 6.
 */
class StripeGateway implements PaymentGatewayInterface
{
    private string $secretKey;

    private bool $enabled;

    public function __construct()
    {
        $gateway = PaymentGateway::where('slug', 'stripe')->first();

        $this->enabled = (bool) ($gateway?->is_enabled ?? settings('stripe_enabled', false));
        $this->secretKey = $gateway?->getCredential('secret_key') ?: settings('stripe_secret_key', '');
    }

    public function getName(): string
    {
        return 'stripe';
    }

    public function isConfigured(): bool
    {
        return $this->enabled && ! empty($this->secretKey);
    }

    public function createSubscription(Plan $plan, User $user, array $paymentData): SubscriptionResult
    {
        if (! $this->isConfigured()) {
            return new SubscriptionResult(false, error: 'Stripe is not configured');
        }

        $billingCycle = $paymentData['billing_cycle'] ?? 'monthly';
        $priceId = $billingCycle === 'yearly'
            ? $plan->stripe_price_yearly_id
            : $plan->stripe_price_monthly_id;

        if (! $priceId) {
            return new SubscriptionResult(false, error: 'Stripe price ID not configured for this plan');
        }

        try {
            // Create or get Stripe customer
            $customerId = $paymentData['stripe_customer_id'] ?? $this->getOrCreateCustomer($user);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
            ])->asForm()->post('https://api.stripe.com/v1/subscriptions', [
                'customer' => $customerId,
                'items[0][price]' => $priceId,
                'payment_behavior' => 'default_incomplete',
                'expand[]' => 'latest_invoice.payment_intent',
            ]);

            if ($response->failed()) {
                return new SubscriptionResult(false, error: $response->json('error.message', 'Stripe error'));
            }

            $data = $response->json();

            return new SubscriptionResult(
                success: true,
                subscriptionId: $data['id'],
                clientSecret: $data['latest_invoice']['payment_intent']['client_secret'] ?? null,
            );
        } catch (\Exception $e) {
            return new SubscriptionResult(false, error: $e->getMessage());
        }
    }

    public function createOneTimePayment(float $amount, string $currency, User $user): PaymentResult
    {
        if (! $this->isConfigured()) {
            return new PaymentResult(false, error: 'Stripe is not configured');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
            ])->asForm()->post('https://api.stripe.com/v1/payment_intents', [
                'amount' => (int) ($amount * 100), // cents
                'currency' => strtolower($currency),
                'metadata[user_id]' => $user->id,
            ]);

            if ($response->failed()) {
                return new PaymentResult(false, error: $response->json('error.message', 'Stripe error'));
            }

            $data = $response->json();

            return new PaymentResult(
                success: true,
                paymentId: $data['id'],
                clientSecret: $data['client_secret'],
            );
        } catch (\Exception $e) {
            return new PaymentResult(false, error: $e->getMessage());
        }
    }

    public function cancelSubscription(string $subscriptionId): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->secretKey,
        ])->delete("https://api.stripe.com/v1/subscriptions/{$subscriptionId}");

        return $response->successful();
    }

    public function handleWebhook(Request $request): void
    {
        // Stripe webhook handler — verify signature, process events
        // Events: invoice.paid, customer.subscription.updated/deleted, payment_intent.succeeded
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = settings('stripe_webhook_secret', '');

        // TODO: Verify signature and process events
    }

    public function refund(string $transactionId, float $amount): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->secretKey,
        ])->asForm()->post('https://api.stripe.com/v1/refunds', [
            'payment_intent' => $transactionId,
            'amount' => (int) ($amount * 100),
        ]);

        return $response->successful();
    }

    private function getOrCreateCustomer(User $user): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->secretKey,
        ])->asForm()->post('https://api.stripe.com/v1/customers', [
            'email' => $user->email,
            'name' => $user->name,
            'metadata[user_id]' => $user->id,
        ]);

        return $response->json('id');
    }
}
