<?php

namespace App\Http\Controllers\Admin\Premium\Subscriptions;

use App\Http\Controllers\Controller;
use App\Models\GatewaySubscription;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionManagementController extends Controller
{
    public function index(Request $request): Response
    {

        $filters = [
            'status' => trim((string) $request->string('status')->value()),
            'gateway' => trim((string) $request->string('gateway')->value()),
            'plan' => trim((string) $request->string('plan')->value()),
        ];

        $gatewaySubscriptions = GatewaySubscription::query()
            ->with([
                'user:id,ulid,name,email',
                'plan:id,name',
            ])
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['plan'] !== '', fn ($query) => $query->where('plan_id', (int) $filters['plan']))
            ->when($filters['gateway'] !== '', fn ($query) => $query->where('gateway', $filters['gateway']))
            ->latest('created_at')
            ->get();

        $subscriptionUserIds = GatewaySubscription::query()
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        $syntheticSubscriptions = User::query()
            ->with('plan:id,name')
            ->whereNotNull('plan_id')
            ->whereNotIn('id', $subscriptionUserIds)
            ->where(function ($query) {
                $query
                    ->whereHas('plan', fn ($planQuery) => $planQuery->where('price_monthly', '>', 0)->orWhere('price_yearly', '>', 0))
                    ->orWhereNotNull('subscription_status');
            })
            ->when($filters['status'] !== '', fn ($query) => $query->where('subscription_status', $filters['status']))
            ->when($filters['plan'] !== '', fn ($query) => $query->where('plan_id', (int) $filters['plan']))
            ->when($filters['gateway'] !== '', fn ($query) => $query->whereRaw('1 = 0'))
            ->latest('updated_at')
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => 'user-'.$user->id,
                    'user_id' => $user->id,
                    'plan_id' => $user->plan_id,
                    'billing_cycle' => null,
                    'status' => $user->subscription_status ?: 'active',
                    'gateway' => null,
                    'gateway_subscription_id' => null,
                    'amount' => null,
                    'currency' => null,
                    'trial_ends_at' => optional($user->trial_ends_at)?->toISOString(),
                    'current_period_start' => null,
                    'current_period_end' => optional($user->subscription_ends_at)?->toISOString(),
                    'cancelled_at' => null,
                    'created_at' => optional($user->updated_at ?? $user->created_at)?->toISOString(),
                    'user' => [
                        'ulid' => $user->ulid,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                    'plan' => $user->plan ? [
                        'id' => $user->plan->id,
                        'name' => $user->plan->name,
                    ] : null,
                ];
            });

        $subscriptionRows = $gatewaySubscriptions
            ->map(fn (GatewaySubscription $subscription) => [
                'id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'plan_id' => $subscription->plan_id,
                'billing_cycle' => $subscription->billing_cycle,
                'status' => $subscription->status,
                'gateway' => $subscription->gateway,
                'gateway_subscription_id' => $subscription->gateway_subscription_id,
                'amount' => $subscription->amount,
                'currency' => $subscription->currency,
                'trial_ends_at' => optional($subscription->trial_ends_at)?->toISOString(),
                'current_period_start' => optional($subscription->current_period_start)?->toISOString(),
                'current_period_end' => optional($subscription->current_period_end)?->toISOString(),
                'cancelled_at' => optional($subscription->cancelled_at)?->toISOString(),
                'created_at' => optional($subscription->created_at)?->toISOString(),
                'user' => $subscription->user ? [
                    'ulid' => $subscription->user->ulid,
                    'name' => $subscription->user->name,
                    'email' => $subscription->user->email,
                ] : null,
                'plan' => $subscription->plan ? [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                ] : null,
            ])
            ->concat($syntheticSubscriptions)
            ->sortByDesc('created_at')
            ->values();

        $perPage = 20;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $paginatedSubscriptions = new LengthAwarePaginator(
            $subscriptionRows->forPage($currentPage, $perPage)->values(),
            $subscriptionRows->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        $gateways = GatewaySubscription::query()
            ->select('gateway')
            ->whereNotNull('gateway')
            ->where('gateway', '!=', '')
            ->distinct()
            ->orderBy('gateway')
            ->pluck('gateway')
            ->map(fn (string $gateway) => [
                'value' => $gateway,
                'label' => str($gateway)->replace(['_', '-'], ' ')->title()->toString(),
            ])
            ->values();

        return Inertia::render('Admin/Premium/Subscriptions/Index', [
            'subscriptions' => [
                'data' => $paginatedSubscriptions->items(),
                'links' => $paginatedSubscriptions->linkCollection(),
                'from' => $paginatedSubscriptions->firstItem(),
                'to' => $paginatedSubscriptions->lastItem(),
                'total' => $paginatedSubscriptions->total(),
            ],
            'filters' => $filters,
            'plans' => Plan::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'gateways' => $gateways,
        ]);
    }

    public function deactivate(User $user): RedirectResponse
    {
        $subscription = GatewaySubscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'trialing', 'past_due', 'cancelled', 'canceled'])
            ->latest('created_at')
            ->first();

        if ($subscription) {
            $this->cancelGatewaySubscription($subscription);

            GatewaySubscription::query()
                ->where('user_id', $user->id)
                ->whereIn('status', ['active', 'trialing', 'past_due', 'cancelled', 'canceled'])
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'current_period_end' => now(),
                ]);
        } elseif ($user->plan_id) {
            GatewaySubscription::query()->create([
                'user_id' => $user->id,
                'plan_id' => $user->plan_id,
                'billing_cycle' => 'monthly',
                'status' => 'cancelled',
                'gateway' => 'manual',
                'gateway_subscription_id' => null,
                'amount' => 0,
                'currency' => settings('currency_code', 'USD'),
                'trial_ends_at' => $user->trial_ends_at,
                'current_period_start' => null,
                'current_period_end' => now(),
                'cancelled_at' => now(),
            ]);
        }

        $user->update([
            'plan_id' => null,
            'subscription_status' => 'none',
            'subscription_ends_at' => now(),
            'trial_ends_at' => null,
        ]);

        return back()->with('success', translate('Subscription cancelled successfully.'));
    }

    private function cancelGatewaySubscription(GatewaySubscription $subscription): void
    {
        if (! $subscription->gateway_subscription_id) {
            return;
        }

        $gateway = PaymentGateway::query()
            ->where('slug', $subscription->gateway)
            ->where('is_enabled', true)
            ->first();

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
                '2checkout' => $this->cancel2Checkout($subscription->gateway_subscription_id),
                default => null,
            };
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Admin gateway subscription cancellation failed', [
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
                    'reason' => 'Admin cancelled subscription',
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
                'effective_from' => 'immediately',
            ])->throw();
    }

    private function cancelRazorpay(PaymentGateway $gateway, string $gatewaySubscriptionId): void
    {
        $key = $gateway->getCredential('key_id');
        $secret = $gateway->getCredential('key_secret');

        Http::withBasicAuth($key, $secret)
            ->post('https://api.razorpay.com/v1/subscriptions/'.$gatewaySubscriptionId.'/cancel', [
                'cancel_at_cycle_end' => 0,
            ])->throw();
    }

    private function cancelPaystack(PaymentGateway $gateway, string $gatewaySubscriptionId): void
    {
        $secret = $gateway->getCredential('secret_key');

        Http::withToken($secret)
            ->post('https://api.paystack.co/subscription/'.$gatewaySubscriptionId.'/disable')
            ->throw();
    }

    private function cancel2Checkout(string $gatewaySubscriptionId): void
    {
        \Illuminate\Support\Facades\Log::info('2Checkout admin cancellation requested', [
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
