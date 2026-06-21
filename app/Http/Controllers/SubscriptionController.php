<?php

namespace App\Http\Controllers;

use App\Models\GatewaySubscription;
use App\Models\PaymentGateway;
use App\Services\Subscription\SubscriptionLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SubscriptionController extends Controller
{
    public function cancel(Request $request, SubscriptionLifecycleService $lifecycle): RedirectResponse
    {
        $subscription = GatewaySubscription::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['active', 'trialing'])
            ->latest()
            ->firstOrFail();

        $this->cancelGatewaySubscription($subscription);
        $lifecycle->cancelAtPeriodEnd($subscription);

        return back()->with('success', translate('Subscription canceled. Access remains until the current period ends.'));
    }

    private function cancelGatewaySubscription(GatewaySubscription $subscription): void
    {
        if (! $subscription->gateway_subscription_id) {
            return;
        }

        $gateway = PaymentGateway::where('slug', $subscription->gateway)->where('is_enabled', true)->first();

        if (! $gateway) {
            return;
        }

        try {
            match ($subscription->gateway) {
                'paypal' => $this->cancelPayPal($gateway, $subscription->gateway_subscription_id),
                'stripe' => $this->cancelStripe($subscription->gateway_subscription_id),
                'paddle' => $this->cancelPaddle($gateway, $subscription->gateway_subscription_id),
                'razorpay' => $this->cancelRazorpay($gateway, $subscription->gateway_subscription_id),
                'paystack' => $this->cancelPaystack($gateway, $subscription->gateway_subscription_id),
                '2checkout' => $this->cancel2Checkout($gateway, $subscription->gateway_subscription_id),
                default => null,
            };
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Gateway subscription cancellation failed', [
                'gateway' => $subscription->gateway,
                'subscription_id' => $subscription->gateway_subscription_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function cancelPayPal(PaymentGateway $gateway, string $gatewaySubscriptionId): void
    {
        $token = $this->paypalAccessToken($gateway);

        if ($token) {
            Http::withToken($token)
                ->post($this->paypalBaseUrl($gateway).'/v1/billing/subscriptions/'.$gatewaySubscriptionId.'/cancel', [
                    'reason' => 'User requested cancellation',
                ])->throw();
        }
    }

    private function cancelStripe(string $gatewaySubscriptionId): void
    {
        $stripe = new \Stripe\StripeClient(config('cashier.secret'));
        $stripe->subscriptions->cancel($gatewaySubscriptionId, ['prorate' => false]);
    }

    private function cancelPaddle(PaymentGateway $gateway, string $gatewaySubscriptionId): void
    {
        $apiKey = $gateway->getCredential('api_key');
        $baseUrl = $gateway->is_test_mode ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';

        Http::withToken($apiKey)
            ->post($baseUrl.'/v1/subscriptions/'.$gatewaySubscriptionId.'/cancel', [
                'effective_from' => 'next_billing_period',
            ])->throw();
    }

    private function cancelRazorpay(PaymentGateway $gateway, string $gatewaySubscriptionId): void
    {
        $key = $gateway->getCredential('key_id');
        $secret = $gateway->getCredential('key_secret');
        $baseUrl = $gateway->is_test_mode ? 'https://api.razorpay.com/v1' : 'https://api.razorpay.com/v1';

        Http::withBasicAuth($key, $secret)
            ->post($baseUrl.'/subscriptions/'.$gatewaySubscriptionId.'/cancel', [
                'cancel_at_cycle_end' => 1,
            ])->throw();
    }

    private function cancelPaystack(PaymentGateway $gateway, string $gatewaySubscriptionId): void
    {
        $secret = $gateway->getCredential('secret_key');
        $baseUrl = 'https://api.paystack.co';

        Http::withToken($secret)
            ->post($baseUrl.'/subscription/'.$gatewaySubscriptionId.'/disable')
            ->throw();
    }

    private function cancel2Checkout(PaymentGateway $gateway, string $gatewaySubscriptionId): void
    {
        // 2Checkout uses a specific API endpoint for subscription cancellation
        // Implementation depends on the specific 2Checkout API version used
        \Illuminate\Support\Facades\Log::info('2Checkout cancellation requested', [
            'subscription_id' => $gatewaySubscriptionId,
        ]);
    }

    private function paypalAccessToken(PaymentGateway $gateway): ?string
    {
        $clientId = $gateway->getCredential('client_id');
        $clientSecret = $gateway->getCredential('client_secret');

        if (! $clientId || ! $clientSecret) {
            return null;
        }

        $response = Http::withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post($this->paypalBaseUrl($gateway).'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        return $response->successful() ? $response->json('access_token') : null;
    }

    private function paypalBaseUrl(PaymentGateway $gateway): string
    {
        return $gateway->is_test_mode ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }
}
