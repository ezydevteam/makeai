<?php

namespace App\Http\Controllers\Admin;

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
        abort_unless(isProAvailable(), 404);

        return Inertia::render('Admin/Plans/Index', [
            'plans' => Plan::with(['countryPrices' => fn ($query) => $query->orderBy('country_code')])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'countries' => CountryCatalog::countries(app()->getLocale()),
            'currencies' => CountryCatalog::currencies(),
            'settings' => $this->pricingSettings(),
        ]);
    }

    public function update(PlanPricingRequest $request, Plan $plan): RedirectResponse
    {
        abort_unless(isProAvailable(), 404);

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
            'currency_code' => settings('pricing_currency_code', 'USD'),
            'credits' => $data['credits'],
            'features' => $data['features'] ?? [],
            'is_featured' => $data['is_featured'] ?? false,
            'is_active' => $data['is_active'] ?? false,
            'trial_all_countries' => $data['trial_all_countries'] ?? false,
            'trial_days' => $data['trial_days'] ?? 0,
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
        abort_unless(isProAvailable(), 404);

        $data = $request->validate([
            'pricing_show_monthly' => ['boolean'],
            'pricing_show_yearly' => ['boolean'],
            'pricing_show_lifetime' => ['boolean'],
            'pricing_currency_code' => ['required', 'string', 'size:3'],
            'pricing_trial_button_text' => ['required', 'string', 'max:80'],
            'pricing_featured_label_text' => ['required', 'string', 'max:80'],
            'pricing_checkout_button_text' => ['required', 'string', 'max:80'],
        ]);

        foreach ($data as $key => $value) {
            settings_set($key, $value, is_bool($value) ? 'boolean' : 'string', 'pricing');
        }

        return back()->with('success', translate('Pricing settings updated successfully.'));
    }

    private function pricingSettings(): array
    {
        return [
            'pricing_show_monthly' => settings('pricing_show_monthly', true),
            'pricing_show_yearly' => settings('pricing_show_yearly', true),
            'pricing_show_lifetime' => settings('pricing_show_lifetime', true),
            'pricing_currency_code' => settings('pricing_currency_code', 'USD'),
            'pricing_trial_button_text' => settings('pricing_trial_button_text', 'Start Trial'),
            'pricing_featured_label_text' => settings('pricing_featured_label_text', 'Recommended'),
            'pricing_checkout_button_text' => settings('pricing_checkout_button_text', 'Choose Plan'),
        ];
    }
}
