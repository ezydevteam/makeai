<?php

namespace App\Services\Payment;

use App\Models\GatewaySubscription;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cancels (and resumes) subscriptions on the payment gateway side.
 *
 * All remote failures are logged but never thrown: local state must update even
 * if the gateway call fails, otherwise users get stuck unable to cancel.
 * Gateways whose checkout flows only create one-time payments never store a
 * gateway_subscription_id, so they are handled as local-only automatically.
 */
class GatewaySubscriptionCanceller
{
    public function cancelAtPeriodEnd(GatewaySubscription $subscription): void
    {
        $this->cancel($subscription, immediately: false);
    }

    public function cancelImmediately(GatewaySubscription $subscription): void
    {
        $this->cancel($subscription, immediately: true);
    }

    /**
     * Attempt to undo a period-end cancellation on the gateway. Returns true when
     * the gateway resumed billing (or no remote subscription exists), false when
     * the gateway does not support resuming — callers must not reactivate locally
     * in that case, because no further charges would ever arrive.
     */
    public function resume(GatewaySubscription $subscription): bool
    {
        if (! $subscription->gateway_subscription_id) {
            return true;
        }

        $gateway = $this->gatewayRecord($subscription->gateway);

        if (! $gateway) {
            return false;
        }

        try {
            return match ($subscription->gateway) {
                'stripe' => $this->resumeStripe($subscription->gateway_subscription_id),
                'paypal' => $this->resumePayPal($gateway, $subscription->gateway_subscription_id),
                default => false,
            };
        } catch (\Throwable $e) {
            Log::error('Gateway subscription resume failed', [
                'gateway' => $subscription->gateway,
                'subscription_id' => $subscription->gateway_subscription_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function cancel(GatewaySubscription $subscription, bool $immediately): void
    {
        if (! $subscription->gateway_subscription_id) {
            return;
        }

        $gateway = $this->gatewayRecord($subscription->gateway);

        if (! $gateway) {
            return;
        }

        try {
            match ($subscription->gateway) {
                'stripe' => $this->cancelStripe($subscription->gateway_subscription_id, $immediately),
                'paypal' => $this->cancelPayPal($gateway, $subscription->gateway_subscription_id),
                'paddle' => $this->cancelPaddle($gateway, $subscription->gateway_subscription_id, $immediately),
                'razorpay' => $this->cancelRazorpay($gateway, $subscription->gateway_subscription_id, $immediately),
                'paystack' => $this->cancelPaystack($gateway, $subscription->gateway_subscription_id),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::error('Gateway subscription cancellation failed', [
                'gateway' => $subscription->gateway,
                'subscription_id' => $subscription->gateway_subscription_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function gatewayRecord(?string $slug): ?PaymentGateway
    {
        if (! $slug) {
            return null;
        }

        return PaymentGateway::query()->where('slug', $slug)->first();
    }

    // ─── Stripe ─────────────────────────────────

    private function cancelStripe(string $gatewaySubscriptionId, bool $immediately): void
    {
        $stripe = new \Stripe\StripeClient(config('cashier.secret'));

        if ($immediately) {
            $stripe->subscriptions->cancel($gatewaySubscriptionId, ['prorate' => false]);
        } else {
            $stripe->subscriptions->update($gatewaySubscriptionId, ['cancel_at_period_end' => true]);
        }
    }

    private function resumeStripe(string $gatewaySubscriptionId): bool
    {
        $stripe = new \Stripe\StripeClient(config('cashier.secret'));
        $stripe->subscriptions->update($gatewaySubscriptionId, ['cancel_at_period_end' => false]);

        return true;
    }

    // ─── PayPal ─────────────────────────────────

    private function cancelPayPal(PaymentGateway $gateway, string $gatewaySubscriptionId): void
    {
        $token = $this->payPalAccessToken($gateway);

        if ($token) {
            // PayPal cancellation stops all future billing; local access is preserved
            // until the current period end by the lifecycle service.
            Http::withToken($token)
                ->post($this->payPalBaseUrl($gateway).'/v1/billing/subscriptions/'.$gatewaySubscriptionId.'/cancel', [
                    'reason' => 'Cancellation requested',
                ])->throw();
        }
    }

    private function resumePayPal(PaymentGateway $gateway, string $gatewaySubscriptionId): bool
    {
        $token = $this->payPalAccessToken($gateway);

        if (! $token) {
            return false;
        }

        // Activation only works while the subscription is suspended, not after a
        // full cancellation — treat failures as "cannot resume".
        $response = Http::withToken($token)
            ->post($this->payPalBaseUrl($gateway).'/v1/billing/subscriptions/'.$gatewaySubscriptionId.'/activate', [
                'reason' => 'Resuming subscription',
            ]);

        return $response->successful();
    }

    private function payPalAccessToken(PaymentGateway $gateway): ?string
    {
        $clientId = $gateway->getCredential('client_id');
        $clientSecret = $gateway->getCredential('client_secret');

        if (! $clientId || ! $clientSecret) {
            return null;
        }

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post($this->payPalBaseUrl($gateway).'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        return $response->successful() ? $response->json('access_token') : null;
    }

    private function payPalBaseUrl(PaymentGateway $gateway): string
    {
        return $gateway->is_test_mode ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    // ─── Paddle ─────────────────────────────────

    private function cancelPaddle(PaymentGateway $gateway, string $gatewaySubscriptionId, bool $immediately): void
    {
        $apiKey = $gateway->getCredential('api_key');
        $baseUrl = $gateway->is_test_mode ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';

        Http::withToken($apiKey)
            ->post($baseUrl.'/subscriptions/'.$gatewaySubscriptionId.'/cancel', [
                'effective_from' => $immediately ? 'immediately' : 'next_billing_period',
            ])->throw();
    }

    // ─── Razorpay ───────────────────────────────

    private function cancelRazorpay(PaymentGateway $gateway, string $gatewaySubscriptionId, bool $immediately): void
    {
        $key = $gateway->getCredential('key_id');
        $secret = $gateway->getCredential('key_secret');

        Http::withBasicAuth($key, $secret)
            ->post('https://api.razorpay.com/v1/subscriptions/'.$gatewaySubscriptionId.'/cancel', [
                'cancel_at_cycle_end' => $immediately ? 0 : 1,
            ])->throw();
    }

    // ─── Paystack ───────────────────────────────

    private function cancelPaystack(PaymentGateway $gateway, string $gatewaySubscriptionId): void
    {
        $secret = $gateway->getCredential('secret_key');

        // Disabling requires the subscription's email token, which must be fetched first.
        $subscription = Http::withToken($secret)
            ->get('https://api.paystack.co/subscription/'.$gatewaySubscriptionId)
            ->throw();

        $emailToken = $subscription->json('data.email_token');

        Http::withToken($secret)
            ->post('https://api.paystack.co/subscription/disable', [
                'code' => $gatewaySubscriptionId,
                'token' => $emailToken,
            ])->throw();
    }
}
