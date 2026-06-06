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
        abort_unless(isProAvailable(), 404);

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

        if ($subscription->gateway === 'paypal') {
            $token = $this->paypalAccessToken($gateway);

            if ($token) {
                Http::withToken($token)
                    ->post($this->paypalBaseUrl($gateway).'/v1/billing/subscriptions/'.$subscription->gateway_subscription_id.'/cancel', [
                        'reason' => 'User requested cancellation',
                    ]);
            }
        }
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
