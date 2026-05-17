<?php

namespace App\Services\Payment;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * PaymentGatewayInterface — contract for all payment gateways.
 * Ref: AI_SaaS_Master_Prompt Part 6 — Payment Gateways.
 *
 * Each gateway (Stripe, PayPal, Paddle, Razorpay, etc.) MUST implement this.
 */
interface PaymentGatewayInterface
{
    /**
     * Create a recurring subscription.
     */
    public function createSubscription(Plan $plan, User $user, array $paymentData): SubscriptionResult;

    /**
     * Create a one-time payment (credit top-up, lifetime plan).
     */
    public function createOneTimePayment(float $amount, string $currency, User $user): PaymentResult;

    /**
     * Cancel an existing subscription.
     */
    public function cancelSubscription(string $subscriptionId): bool;

    /**
     * Handle incoming webhook from the gateway.
     */
    public function handleWebhook(Request $request): void;

    /**
     * Process a refund.
     */
    public function refund(string $transactionId, float $amount): bool;

    /**
     * Get the gateway name.
     */
    public function getName(): string;

    /**
     * Check if gateway is configured (has API keys).
     */
    public function isConfigured(): bool;
}
