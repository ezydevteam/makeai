<?php

namespace App\Services\Payment;

/**
 * SubscriptionResult — returned by createSubscription().
 */
class SubscriptionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $subscriptionId = null,
        public readonly ?string $redirectUrl = null,     // for redirect-based flows (PayPal)
        public readonly ?string $clientSecret = null,     // for Stripe Elements
        public readonly ?string $error = null,
    ) {}
}
