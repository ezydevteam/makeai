<?php

namespace App\Http\Requests\Admin;

use App\Support\CurrencyCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'bottom_info_text' => ['nullable', 'string', 'max:255'],
            'price_monthly' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'price_yearly' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'price_lifetime' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'original_price_monthly' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'original_price_yearly' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'original_price_lifetime' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'vat_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // Per-plan currency removed entirely — the store uses one base currency.
            'credits' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'features' => ['array'],
            'features.*' => ['nullable', 'string', 'max:255'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'trial_all_countries' => ['boolean'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'stripe_price_monthly_id' => ['nullable', 'string', 'max:255', 'starts_with:price_'],
            'stripe_price_yearly_id' => ['nullable', 'string', 'max:255', 'starts_with:price_'],
            'paypal_plan_monthly_id' => ['nullable', 'string', 'max:255', 'starts_with:P-'],
            'paypal_plan_yearly_id' => ['nullable', 'string', 'max:255', 'starts_with:P-'],
            'country_prices' => ['array'],
            'country_prices.*.id' => ['nullable', 'integer', 'exists:plan_country_prices,id'],
            'country_prices.*.country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            // A country price is CHARGED in this currency, so it has to be one we can
            // actually render (symbol, decimal places, symbol position). `size:3` alone let
            // any three letters through: a typo produced a price the store could not format
            // and a gateway would reject, with nothing to say so until a customer hit it.
            //
            // Validated against CurrencyCatalog — the same list that now populates the admin
            // picker — rather than the `currencies` table, which only seeds four rows and
            // would reject a perfectly valid INR or BRL price.
            'country_prices.*.currency_code' => ['required', 'string', 'size:3', Rule::in(CurrencyCatalog::codes())],
            'country_prices.*.original_price_monthly' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'country_prices.*.original_price_yearly' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'country_prices.*.original_price_lifetime' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'country_prices.*.price_monthly' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'country_prices.*.price_yearly' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'country_prices.*.price_lifetime' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'country_prices.*.vat_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'country_prices.*.trial_monthly_enabled' => ['boolean'],
            'country_prices.*.trial_yearly_enabled' => ['boolean'],
            'country_prices.*.trial_lifetime_enabled' => ['boolean'],
            'country_prices.*.trial_monthly_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'country_prices.*.trial_yearly_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'country_prices.*.trial_lifetime_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'country_prices.*.is_active' => ['boolean'],
            'country_prices.*._delete' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'country_prices.*.currency_code.in' => translate('That is not a currency this store can charge in. Pick one from the list.'),
            'country_prices.*.country_code.regex' => translate('Country must be a two-letter country code.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'features' => collect($this->input('features', []))
                ->filter(fn ($feature) => is_string($feature) && trim($feature) !== '')
                ->map(fn (string $feature) => trim($feature))
                ->values()
                ->all(),
            'country_prices' => collect($this->input('country_prices', []))
                ->map(function (array $row) {
                    $row['country_code'] = strtoupper((string) ($row['country_code'] ?? ''));
                    $row['currency_code'] = strtoupper((string) ($row['currency_code'] ?? ''));

                    return $row;
                })
                ->values()
                ->all(),
        ]);
    }
}
