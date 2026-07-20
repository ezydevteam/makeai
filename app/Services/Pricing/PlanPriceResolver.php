<?php

namespace App\Services\Pricing;

use App\Models\Plan;
use App\Support\CountryCatalog;
use Illuminate\Support\Collection;

class PlanPriceResolver
{
    private function normalizeFeatures(mixed $features): array
    {
        if (is_array($features)) {
            return array_values(array_filter(array_map(function (mixed $feature): string {
                return trim((string) $feature);
            }, $features), fn (string $feature): bool => $feature !== ''));
        }

        if (! is_string($features) || trim($features) === '') {
            return [];
        }

        $decoded = json_decode($features, true);
        if (is_array($decoded)) {
            return $this->normalizeFeatures($decoded);
        }

        $lines = preg_split('/[\r\n,]+/', $features) ?: [];

        return array_values(array_filter(array_map(fn (string $feature): string => trim($feature), $lines), fn (string $feature): bool => $feature !== ''));
    }

    public function resolve(Plan $plan, ?string $countryCode = null): array
    {
        $countryCode = $this->normalizeCountryCode($countryCode);
        $countryPrice = $countryCode
            ? $plan->countryPrices->first(fn ($price) => $price->country_code === $countryCode && $price->is_active)
            : null;

        // CHARGE currency: a country-specific price's currency (an explicit, intended
        // local price), else the single store base currency. This is what the customer
        // is actually billed — checkout re-resolves and charges this, never a display value.
        $currencyCode = strtoupper($countryPrice?->currency_code ?: base_currency());

        // DISPLAY currency: when there is NO explicit country price and auto-localize is
        // on, convert the base charge to the visitor's local currency for display only
        // (billing still happens in the base currency — the confirmed "charge base unless
        // per-country" rule). rate 1.0 / same currency ⇒ not localized.
        $displayCurrency = $currencyCode;
        $displayRate = 1.0;
        if (! $countryPrice && (bool) settings('pricing_auto_localize', true)) {
            $local = CountryCatalog::currencyForCountry($countryCode);
            if ($local && strtoupper($local) !== $currencyCode) {
                $rate = convert_currency(1.0, $currencyCode, strtoupper($local));
                if ($rate > 0 && abs($rate - 1.0) > 1e-9) {
                    $displayCurrency = strtoupper($local);
                    $displayRate = $rate;
                }
            }
        }

        $isLocalized = $displayCurrency !== $currencyCode;

        return [
            'country_code' => $countryCode,
            'country_name' => CountryCatalog::countryName($countryCode),
            'currency_code' => $currencyCode,          // charge currency
            'display_currency_code' => $displayCurrency, // localized display currency
            'is_localized' => $isLocalized,
            'source' => $countryPrice ? 'country' : 'default',
            'monthly' => $this->cycle($plan, $countryPrice, 'monthly', $currencyCode, $displayCurrency, $displayRate),
            'yearly' => $this->cycle($plan, $countryPrice, 'yearly', $currencyCode, $displayCurrency, $displayRate),
            'lifetime' => $this->cycle($plan, $countryPrice, 'lifetime', $currencyCode, $displayCurrency, $displayRate),
        ];
    }

    public function resolveCollection(Collection $plans, ?string $countryCode = null): Collection
    {
        return $plans->map(function (Plan $plan) use ($countryCode) {
            $resolved = $this->resolve($plan, $countryCode);

            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'description' => $plan->description,
                'bottom_info_text' => $plan->bottom_info_text,
                'credits' => $plan->credits,
                'features' => $this->normalizeFeatures($plan->features),
                'is_featured' => $plan->is_featured,
                'is_free' => $plan->is_free,
                'is_active' => $plan->is_active,
                'sort_order' => (int) $plan->sort_order,
                'trial_all_countries' => $plan->trial_all_countries,
                'yearly_savings' => $this->yearlySavings($resolved),
                'pricing' => $resolved,
            ];
        });
    }

    private function cycle(Plan $plan, mixed $countryPrice, string $cycle, string $currencyCode, ?string $displayCurrency = null, float $displayRate = 1.0): array
    {
        $displayCurrency ??= $currencyCode;
        $priceColumn = 'price_'.$cycle;
        $originalColumn = 'original_price_'.$cycle;
        $trialColumn = 'trial_'.$cycle.'_days';
        $defaultPrice = $plan->{$priceColumn};
        $countryValue = $countryPrice?->{$priceColumn};
        $subtotal = (float) ($countryValue === null ? $defaultPrice : $countryValue);
        $trialEnabledColumn = 'trial_'.$cycle.'_enabled';
        $isTrial = $plan->trial_all_countries || (bool) ($countryPrice?->{$trialEnabledColumn} ?? false);
        $originalValue = $countryPrice?->{$originalColumn} ?? $plan->{$originalColumn};
        $originalValue ??= $isTrial && $subtotal > 0 ? $subtotal : null;
        $originalValue ??= $isTrial && $countryValue !== null && (float) $countryValue === 0.0 ? $defaultPrice : null;
        $vatPercentage = (float) ($countryPrice?->vat_percentage ?? $plan->vat_percentage ?? 0);
        $vatAmount = round(($subtotal * $vatPercentage) / 100, 2);
        $totalAmount = round($subtotal + $vatAmount, 2);
        $trialDays = $isTrial ? $this->trialDays($countryPrice?->{$trialColumn}, $cycle, $plan->trial_days) : null;

        // Convert a base-currency figure into the visitor's display currency. Every
        // localized field goes through this, so a localized card never mixes currencies.
        $localize = fn (float $value): float => round($value * $displayRate, 2);

        return [
            'amount' => $isTrial ? 0.0 : $totalAmount,
            'subtotal_amount' => $isTrial ? 0.0 : $subtotal,
            // The cycle's configured list price, BEFORE the trial zeroing above. A
            // cycle the admin never priced resolves to 0 here, which is how checkout
            // tells "free because of a trial/coupon" apart from "not sold at all".
            'list_amount' => $subtotal,
            'original_amount' => $originalValue === null ? null : (float) $originalValue,
            // A trial period is free — no VAT is charged, so don't surface a VAT line.
            'vat_percentage' => $isTrial ? 0.0 : $vatPercentage,
            'vat_amount' => $isTrial ? 0.0 : $vatAmount,
            'formatted' => CountryCatalog::formatMoney($isTrial ? 0 : $totalAmount, $currencyCode),
            'subtotal_formatted' => CountryCatalog::formatMoney($isTrial ? 0 : $subtotal, $currencyCode),
            'original_formatted' => $originalValue === null ? null : CountryCatalog::formatMoney($originalValue, $currencyCode),
            'vat_formatted' => CountryCatalog::formatMoney($isTrial ? 0 : $vatAmount, $currencyCode),

            // What a trial actually renews at once it ends. Distinct from `amount`, which
            // is zeroed for a trial — rendering that in "then renews at X" advertised the
            // plan as renewing at zero.
            'renewal_amount' => $totalAmount,
            'renewal_formatted' => CountryCatalog::formatMoney($totalAmount, $currencyCode),
            'display_renewal_formatted' => CountryCatalog::formatMoney($localize($totalAmount), $displayCurrency),

            // Localized DISPLAY only — billing still happens in currency_code. Each base
            // figure has a localized twin so the card can render entirely in one currency
            // instead of pairing a localized headline with a base-currency VAT line.
            'display_amount' => $isTrial ? 0.0 : $localize($totalAmount),
            'display_formatted' => CountryCatalog::formatMoney($isTrial ? 0 : $localize($totalAmount), $displayCurrency),
            'display_subtotal_amount' => $isTrial ? 0.0 : $localize($subtotal),
            'display_subtotal_formatted' => CountryCatalog::formatMoney($isTrial ? 0 : $localize($subtotal), $displayCurrency),
            'display_vat_formatted' => CountryCatalog::formatMoney($isTrial ? 0 : $localize($vatAmount), $displayCurrency),
            'display_original_formatted' => $originalValue === null ? null : CountryCatalog::formatMoney($localize((float) $originalValue), $displayCurrency),

            'uses_default' => $countryValue === null,
            'is_trial' => $isTrial,
            'trial_days' => $trialDays,
        ];
    }

    private function trialDays(?int $configuredDays, string $cycle, ?int $planTrialDays): int
    {
        if ($configuredDays !== null) {
            return max(1, $configuredDays);
        }

        if ($planTrialDays !== null && $planTrialDays > 0) {
            return $planTrialDays;
        }

        return match ($cycle) {
            'yearly' => 360,
            default => 30,
        };
    }

    private function yearlySavings(array $resolved): int
    {
        $monthly = (float) $resolved['monthly']['amount'];
        $yearly = (float) $resolved['yearly']['amount'];

        if ($monthly <= 0 || $yearly <= 0) {
            return 0;
        }

        return max(0, (int) round((1 - ($yearly / ($monthly * 12))) * 100));
    }

    private function normalizeCountryCode(?string $countryCode): ?string
    {
        if (! is_string($countryCode) || strlen($countryCode) !== 2) {
            return null;
        }

        return strtoupper($countryCode);
    }
}
