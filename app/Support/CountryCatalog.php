<?php

namespace App\Support;

use NumberFormatter;

class CountryCatalog
{
    public static function countries(string $locale = 'en'): array
    {
        $countries = [];

        if (class_exists(\ResourceBundle::class)) {
            foreach (\ResourceBundle::getLocales('') ?: [] as $availableLocale) {
                $countryCode = locale_get_region($availableLocale);

                if (! is_string($countryCode) || strlen($countryCode) !== 2) {
                    continue;
                }

                $name = locale_get_display_region('-'.$countryCode, $locale);

                if (is_string($name) && $name !== '') {
                    $countries[$countryCode] = [
                        'code' => $countryCode,
                        'name' => $name,
                    ];
                }
            }
        }

        if ($countries === []) {
            $countries = [
                'US' => ['code' => 'US', 'name' => 'United States'],
                'BD' => ['code' => 'BD', 'name' => 'Bangladesh'],
                'IN' => ['code' => 'IN', 'name' => 'India'],
                'GB' => ['code' => 'GB', 'name' => 'United Kingdom'],
                'CA' => ['code' => 'CA', 'name' => 'Canada'],
                'AU' => ['code' => 'AU', 'name' => 'Australia'],
            ];
        }

        uasort($countries, fn (array $a, array $b) => strnatcasecmp($a['name'], $b['name']));

        return array_values($countries);
    }

    public static function currencies(): array
    {
        return [
            'USD', 'EUR', 'GBP', 'BDT', 'INR', 'PKR', 'NPR', 'LKR', 'CAD', 'AUD',
            'NZD', 'SGD', 'MYR', 'IDR', 'PHP', 'THB', 'VND', 'JPY', 'KRW', 'CNY',
            'HKD', 'AED', 'SAR', 'QAR', 'KWD', 'BHD', 'OMR', 'TRY', 'ZAR', 'NGN',
            'KES', 'EGP', 'MAD', 'BRL', 'MXN', 'ARS', 'CLP', 'COP', 'PEN', 'CHF',
            'SEK', 'NOK', 'DKK', 'PLN', 'CZK', 'HUF', 'RON',
        ];
    }

    public static function countryName(?string $countryCode, string $locale = 'en'): ?string
    {
        if (! is_string($countryCode) || strlen($countryCode) !== 2) {
            return null;
        }

        $name = locale_get_display_region('-'.strtoupper($countryCode), $locale);

        return is_string($name) && $name !== '' ? $name : strtoupper($countryCode);
    }

    public static function formatMoney(float|int|string|null $amount, string $currencyCode): string
    {
        $amount = (float) ($amount ?? 0);
        $currencyCode = strtoupper($currencyCode);

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter(app()->getLocale() ?: 'en', NumberFormatter::CURRENCY);

            $formatted = $formatter->formatCurrency($amount, $currencyCode);

            if (is_string($formatted)) {
                return $formatted;
            }
        }

        return $currencyCode.' '.number_format($amount, 2);
    }
}
