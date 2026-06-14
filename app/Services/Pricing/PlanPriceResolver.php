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

        $currencyCode = strtoupper($countryPrice?->currency_code ?: settings('pricing_currency_code', $plan->currency_code ?: 'USD'));

        return [
            'country_code' => $countryCode,
            'country_name' => CountryCatalog::countryName($countryCode),
            'currency_code' => $currencyCode,
            'source' => $countryPrice ? 'country' : 'default',
            'monthly' => $this->cycle($plan, $countryPrice, 'monthly', $currencyCode),
            'yearly' => $this->cycle($plan, $countryPrice, 'yearly', $currencyCode),
            'lifetime' => $this->cycle($plan, $countryPrice, 'lifetime', $currencyCode),
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
                'trial_all_countries' => $plan->trial_all_countries,
                'yearly_savings' => $this->yearlySavings($resolved),
                'pricing' => $resolved,
            ];
        });
    }

    private function cycle(Plan $plan, mixed $countryPrice, string $cycle, string $currencyCode): array
    {
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

        return [
            'amount' => $isTrial ? 0.0 : $totalAmount,
            'subtotal_amount' => $isTrial ? 0.0 : $subtotal,
            'original_amount' => $originalValue === null ? null : (float) $originalValue,
            'vat_percentage' => $vatPercentage,
            'vat_amount' => $vatAmount,
            'formatted' => CountryCatalog::formatMoney($isTrial ? 0 : $totalAmount, $currencyCode),
            'subtotal_formatted' => CountryCatalog::formatMoney($isTrial ? 0 : $subtotal, $currencyCode),
            'original_formatted' => $originalValue === null ? null : CountryCatalog::formatMoney($originalValue, $currencyCode),
            'vat_formatted' => CountryCatalog::formatMoney($vatAmount, $currencyCode),
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
