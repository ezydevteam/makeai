<?php

namespace App\Http\Controllers\Admin\Premium;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanPricingRequest;
use App\Models\Plan;
use App\Models\PlanCountryPrice;
use App\Support\CountryCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(): Response
    {

        return Inertia::render('Admin/Premium/Plans', [
            'plans' => Plan::with(['countryPrices' => fn ($query) => $query->orderBy('country_code')])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Plan $plan) => $this->planPayload($plan)),
            'countries' => CountryCatalog::countries(app()->getLocale()),
            'currencies' => CountryCatalog::currencies(),
            'settings' => $this->pricingSettings(),
        ]);
    }



    public function update(PlanPricingRequest $request, Plan $plan): RedirectResponse
    {
        $data = $request->validated();

        if (($data['is_featured'] ?? false) === true) {
            Plan::whereKeyNot($plan->id)->update(['is_featured' => false]);
        }

        $plan->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'bottom_info_text' => $data['bottom_info_text'] ?? null,
            'price_monthly' => $data['price_monthly'],
            'price_yearly' => $data['price_yearly'],
            'price_lifetime' => $data['price_lifetime'] ?? null,
            'original_price_monthly' => $data['original_price_monthly'] ?? null,
            'original_price_yearly' => $data['original_price_yearly'] ?? null,
            'original_price_lifetime' => $data['original_price_lifetime'] ?? null,
            'vat_percentage' => $data['vat_percentage'] ?? 0,
            // No per-plan currency — the store uses one base currency (Admin → Settings).
            'credits' => $data['credits'],
            'features' => $data['features'] ?? [],
            'is_featured' => $data['is_featured'] ?? false,
            'is_active' => $data['is_active'] ?? false,
            'trial_all_countries' => $data['trial_all_countries'] ?? false,
            'trial_days' => $data['trial_days'] ?? 0,
            'stripe_price_monthly_id' => $data['stripe_price_monthly_id'] ?? null,
            'stripe_price_yearly_id' => $data['stripe_price_yearly_id'] ?? null,
            'paypal_plan_monthly_id' => $data['paypal_plan_monthly_id'] ?? null,
            'paypal_plan_yearly_id' => $data['paypal_plan_yearly_id'] ?? null,
        ]);

        $seenCountries = [];

        foreach ($data['country_prices'] ?? [] as $row) {
            if (($row['_delete'] ?? false) === true) {
                if (! empty($row['id'])) {
                    PlanCountryPrice::where('plan_id', $plan->id)->whereKey($row['id'])->delete();
                }

                continue;
            }

            if (in_array($row['country_code'], $seenCountries, true)) {
                continue;
            }

            $seenCountries[] = $row['country_code'];

            PlanCountryPrice::updateOrCreate(
                [
                    'plan_id' => $plan->id,
                    'country_code' => $row['country_code'],
                ],
                [
                    'currency_code' => $row['currency_code'],
                    'original_price_monthly' => $row['original_price_monthly'] ?? null,
                    'original_price_yearly' => $row['original_price_yearly'] ?? null,
                    'original_price_lifetime' => $row['original_price_lifetime'] ?? null,
                    'price_monthly' => $row['price_monthly'] ?? null,
                    'price_yearly' => $row['price_yearly'] ?? null,
                    'price_lifetime' => $row['price_lifetime'] ?? null,
                    'vat_percentage' => $row['vat_percentage'] ?? null,
                    'trial_monthly_enabled' => $row['trial_monthly_enabled'] ?? false,
                    'trial_yearly_enabled' => $row['trial_yearly_enabled'] ?? false,
                    'trial_lifetime_enabled' => $row['trial_lifetime_enabled'] ?? false,
                    'trial_monthly_days' => $row['trial_monthly_days'] ?? null,
                    'trial_yearly_days' => $row['trial_yearly_days'] ?? null,
                    'trial_lifetime_days' => $row['trial_lifetime_days'] ?? null,
                    'is_active' => $row['is_active'] ?? true,
                ]
            );
        }

        return back()->with('success', translate('Plan pricing updated successfully.'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {

        $data = $request->validate([
            'pricing_show_monthly' => ['boolean'],
            'pricing_show_yearly' => ['boolean'],
            'pricing_show_lifetime' => ['boolean'],
            // Currency is no longer set here — it's controlled once in Admin → Settings.
            'pricing_auto_localize' => ['boolean'],
            'pricing_trial_button_text' => ['required', 'string', 'max:80'],
            'pricing_featured_label_text' => ['required', 'string', 'max:80'],
            'pricing_checkout_button_text' => ['required', 'string', 'max:80'],
            // What a newly-registered user gets: 'none', 'custom' (governed by the
            // per-user limits in AI Settings), or a plan id to auto-assign + grant.
            'registration_default_plan' => ['sometimes', 'string', 'max:20'],
            // Same key shared with Credit Settings (top-ups). Optional here so an
            // older form that omits it still saves; persisted in the 'billing' group.
            'credit_price_per_unit' => ['sometimes', 'numeric', 'min:0.0001', 'max:999.9999'],
        ]);

        foreach ($data as $key => $value) {
            if ($key === 'credit_price_per_unit') {
                continue; // shared billing setting — saved separately below
            }
            settings_set($key, $value, is_bool($value) ? 'boolean' : 'string', 'pricing');
        }

        if (array_key_exists('credit_price_per_unit', $data)) {
            settings_set('credit_price_per_unit', $data['credit_price_per_unit'], 'string', 'billing');
        }

        return back()->with('success', translate('Pricing settings updated successfully.'));
    }

    private function pricingSettings(): array
    {
        return [
            'pricing_show_monthly' => settings('pricing_show_monthly', true),
            'pricing_show_yearly' => settings('pricing_show_yearly', true),
            'pricing_show_lifetime' => settings('pricing_show_lifetime', true),
            // Base currency is read-only here (controlled in Admin → Settings); still
            // provided so the plan editor can format prices and label the currency.
            'pricing_currency_code' => base_currency(),
            'pricing_auto_localize' => (bool) settings('pricing_auto_localize', true),
            'pricing_trial_button_text' => settings('pricing_trial_button_text', 'Start Trial'),
            'pricing_featured_label_text' => settings('pricing_featured_label_text', 'Recommended'),
            'pricing_checkout_button_text' => settings('pricing_checkout_button_text', 'Choose Plan'),
            // What a new registrant gets: 'none' | 'custom' | '<plan id>'.
            'registration_default_plan' => (string) settings('registration_default_plan', 'none'),
            // Retail value of one credit (base currency) — used to flag plans that
            // sell credits far below their anchored value (margin sanity check).
            'credit_price_per_unit' => (float) settings('credit_price_per_unit', 0.01),
        ];
    }

    private function planPayload(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'description' => $plan->description,
            'bottom_info_text' => $plan->bottom_info_text,
            'price_monthly' => $plan->price_monthly,
            'price_yearly' => $plan->price_yearly,
            'price_lifetime' => $plan->price_lifetime,
            'original_price_monthly' => $plan->original_price_monthly,
            'original_price_yearly' => $plan->original_price_yearly,
            'original_price_lifetime' => $plan->original_price_lifetime,
            'vat_percentage' => $plan->vat_percentage,
            'credits' => $plan->credits,
            'features' => $plan->features,
            'trial_days' => $plan->trial_days,
            'trial_all_countries' => $plan->trial_all_countries,
            'stripe_price_monthly_id' => $plan->stripe_price_monthly_id,
            'stripe_price_yearly_id' => $plan->stripe_price_yearly_id,
            'paypal_plan_monthly_id' => $plan->paypal_plan_monthly_id,
            'paypal_plan_yearly_id' => $plan->paypal_plan_yearly_id,
            'is_featured' => $plan->is_featured,
            'is_active' => $plan->is_active,
            'country_prices' => $plan->countryPrices->map(fn (PlanCountryPrice $row) => [
                'id' => $row->id,
                'country_code' => $row->country_code,
                'currency_code' => $row->currency_code,
                'original_price_monthly' => $row->original_price_monthly,
                'original_price_yearly' => $row->original_price_yearly,
                'original_price_lifetime' => $row->original_price_lifetime,
                'price_monthly' => $row->price_monthly,
                'price_yearly' => $row->price_yearly,
                'price_lifetime' => $row->price_lifetime,
                'vat_percentage' => $row->vat_percentage,
                'trial_monthly_enabled' => $row->trial_monthly_enabled,
                'trial_yearly_enabled' => $row->trial_yearly_enabled,
                'trial_lifetime_enabled' => $row->trial_lifetime_enabled,
                'trial_monthly_days' => $row->trial_monthly_days,
                'trial_yearly_days' => $row->trial_yearly_days,
                'trial_lifetime_days' => $row->trial_lifetime_days,
                'is_active' => $row->is_active,
            ])->values()->all(),
        ];
    }
}
