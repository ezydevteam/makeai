<?php

namespace App\Http\Controllers;

use App\Models\GatewaySubscription;
use App\Models\Plan;
use App\Services\Pricing\PlanPriceResolver;
use Inertia\Inertia;

class PricingController extends Controller
{
    public function index(PlanPriceResolver $resolver)
    {
        $countryCode = request()->attributes->get('pricing_country', session('pricing_country'));

        $plans = Plan::active()
            ->with('countryPrices')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Pricing', [
            'plans' => $resolver->resolveCollection($plans, $countryCode)->values(),
            'pricingCountry' => $countryCode,
            'currentSubscription' => $this->currentSubscription(),
            'settings' => [
                'pricing_show_monthly' => settings('pricing_show_monthly', true),
                'pricing_show_yearly' => settings('pricing_show_yearly', true),
                'pricing_show_lifetime' => settings('pricing_show_lifetime', true),
                'pricing_currency_code' => base_currency(),
                'pricing_auto_localize' => (bool) settings('pricing_auto_localize', true),
                'pricing_trial_button_text' => settings('pricing_trial_button_text', 'Start Trial'),
                'pricing_featured_label_text' => settings('pricing_featured_label_text', 'Recommended'),
                'pricing_checkout_button_text' => settings('pricing_checkout_button_text', 'Choose Plan'),
            ],
        ]);
    }

    /**
     * Current-plan context for the pricing page so it can render Manage /
     * Upgrade / Downgrade buttons and any pending scheduled-change note.
     */
    private function currentSubscription(): ?array
    {
        $user = request()->user();

        if (! $user || $user instanceof \App\Models\Admin) {
            return null;
        }

        $subscription = GatewaySubscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [GatewaySubscription::STATUS_ACTIVE, GatewaySubscription::STATUS_TRIALING])
            ->with('scheduledPlan:id,slug,name')
            ->latest()
            ->first();

        return [
            'plan_id' => $user->plan_id,
            'is_pro' => isProAvailable() && $user->isPro(),
            'billing_cycle' => $subscription?->billing_cycle,
            // A live recurring subscription (Stripe or PayPal) changes plan in
            // place; everything else upgrades through checkout.
            'is_recurring_in_place' => $subscription
                ? app(\App\Services\Payment\GatewaySubscriptionModifier::class)->supportsInPlace($subscription)
                : false,
            'scheduled_plan_slug' => $subscription?->scheduledPlan?->slug,
            'scheduled_plan_name' => $subscription?->scheduledPlan?->name,
            'scheduled_change_at' => $subscription?->scheduled_change_at?->toISOString(),
        ];
    }
}
