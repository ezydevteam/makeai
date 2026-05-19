<?php

namespace App\Http\Controllers;

use App\Models\CreditPack;
use App\Models\Plan;
use App\Services\Pricing\PlanPriceResolver;
use App\Support\CountryCatalog;
use Inertia\Inertia;

class PricingController extends Controller
{
    public function index(PlanPriceResolver $resolver)
    {
        abort_unless(isProAvailable(), 404);

        $countryCode = request()->attributes->get('pricing_country', session('pricing_country'));

        $plans = Plan::active()
            ->with('countryPrices')
            ->orderBy('sort_order')
            ->get();

        $creditPackCurrency = strtoupper((string) settings('pricing_currency_code', 'USD'));
        $creditPacks = CreditPack::active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CreditPack $pack) => [
                'id' => $pack->id,
                'name' => $pack->name,
                'credits' => $pack->credits,
                'price' => $pack->price,
                'formatted_price' => CountryCatalog::formatMoney($pack->price, $creditPackCurrency),
                'is_popular' => $pack->is_popular,
            ]);

        return Inertia::render('Pricing', [
            'plans' => $resolver->resolveCollection($plans, $countryCode)->values(),
            'creditPacks' => $creditPacks,
            'pricingCountry' => $countryCode,
            'settings' => [
                'pricing_show_monthly' => settings('pricing_show_monthly', true),
                'pricing_show_yearly' => settings('pricing_show_yearly', true),
                'pricing_show_lifetime' => settings('pricing_show_lifetime', true),
                'pricing_currency_code' => settings('pricing_currency_code', 'USD'),
                'pricing_trial_button_text' => settings('pricing_trial_button_text', 'Start Trial'),
                'pricing_featured_label_text' => settings('pricing_featured_label_text', 'Recommended'),
                'pricing_checkout_button_text' => settings('pricing_checkout_button_text', 'Choose Plan'),
            ],
        ]);
    }
}
