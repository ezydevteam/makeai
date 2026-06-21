<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\Pricing\PlanPriceResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StripeController extends Controller
{
    public function checkout(Request $request, PlanPriceResolver $resolver): Response|RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'max:120'],
            'billing' => ['required', 'string', 'in:monthly,yearly,lifetime'],
        ]);

        $user = $request->user();
        $plan = Plan::active()->where('slug', $validated['plan'])->firstOrFail();
        $countryCode = $request->attributes->get('pricing_country', session('pricing_country'));
        $pricing = $resolver->resolve($plan, $countryCode);
        $cycle = $pricing[$validated['billing']];
        $billing = $validated['billing'];

        // Create pending payment record for unified lifecycle tracking (bridges Cashier and custom GatewaySubscription)
        $payment = Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'gateway' => 'stripe',
            'amount' => (float) $cycle['amount'],
            'currency' => $pricing['currency_code'],
            'status' => 'pending',
            'type' => 'subscription',
            'metadata' => [
                'billing_cycle' => $billing,
                'pricing_country' => $pricing['country_code'],
                'pricing_source' => $pricing['source'],
                'is_trial' => ! empty($cycle['is_trial']),
            ],
        ]);

        $stripePriceId = $billing === 'monthly' ? $plan->stripe_price_monthly_id : $plan->stripe_price_yearly_id;

        if ($billing === 'lifetime' || ! $stripePriceId) {
            return $user->checkout(
                [config('cashier.currency') => (int) round(((float) $cycle['amount']) * 100)],
                [
                    'success_url' => route('user.dashboard').'?checkout=success',
                    'cancel_url' => route('pricing').'?billing='.$billing,
                    'metadata' => [
                        'plan_slug' => $plan->slug, 
                        'billing_cycle' => $billing,
                        'payment_id' => $payment->ulid,
                    ],
                ],
            );
        }

        return $user->checkout(
            [$stripePriceId => 1],
            [
                'success_url' => route('user.dashboard').'?checkout=success',
                'cancel_url' => route('pricing').'?billing='.$billing,
                'metadata' => [
                    'plan_slug' => $plan->slug, 
                    'billing_cycle' => $billing,
                    'payment_id' => $payment->ulid,
                ],
            ],
        );
    }

    public function billingPortal(Request $request): RedirectResponse
    {
        return $request->user()->redirectToBillingPortal(route('user.dashboard'));
    }

    public function success(Request $request): Response
    {
        return Inertia::render('Checkout/Success');
    }
}
