<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
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
        abort_unless(isProAvailable(), 404);

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

        $stripePriceId = $billing === 'monthly' ? $plan->stripe_price_monthly_id : $plan->stripe_price_yearly_id;

        if ($billing === 'lifetime' || ! $stripePriceId) {
            return $user->checkout(
                [config('cashier.currency') => (int) round(((float) $cycle['amount']) * 100)],
                [
                    'success_url' => route('user.dashboard').'?checkout=success',
                    'cancel_url' => route('pricing').'?billing='.$billing,
                    'metadata' => ['plan_slug' => $plan->slug, 'billing_cycle' => $billing],
                ],
            );
        }

        return $user->checkout(
            [$stripePriceId => 1],
            [
                'success_url' => route('user.dashboard').'?checkout=success',
                'cancel_url' => route('pricing').'?billing='.$billing,
                'metadata' => ['plan_slug' => $plan->slug, 'billing_cycle' => $billing],
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
