<?php

namespace App\Support;

use App\Models\Currency;
use NumberFormatter;

class CountryCatalog
{
    private const CURRENCY_POSITION_BEFORE = 'before';

    private const CURRENCY_POSITION_BEFORE_WITH_SPACE = 'before_with_space';

    private const CURRENCY_POSITION_AFTER = 'after';

    private const CURRENCY_POSITION_AFTER_WITH_SPACE = 'after_with_space';

    /** @var array<string, array{symbol: string, decimals: int}> */
    private static array $currencyFormats = [];

    private static ?string $currencyPosition = null;

    public static function countries(string $locale = 'en'): array
    {
        $countries = [];

        if (extension_loaded('intl') && class_exists(\ResourceBundle::class) && function_exists('locale_get_region') && function_exists('locale_get_display_region')) {
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
                'DE' => ['code' => 'DE', 'name' => 'Germany'],
                'FR' => ['code' => 'FR', 'name' => 'France'],
                'IT' => ['code' => 'IT', 'name' => 'Italy'],
                'ES' => ['code' => 'ES', 'name' => 'Spain'],
                'NL' => ['code' => 'NL', 'name' => 'Netherlands'],
                'PK' => ['code' => 'PK', 'name' => 'Pakistan'],
                'NP' => ['code' => 'NP', 'name' => 'Nepal'],
                'LK' => ['code' => 'LK', 'name' => 'Sri Lanka'],
                'NZ' => ['code' => 'NZ', 'name' => 'New Zealand'],
                'SG' => ['code' => 'SG', 'name' => 'Singapore'],
                'MY' => ['code' => 'MY', 'name' => 'Malaysia'],
                'ID' => ['code' => 'ID', 'name' => 'Indonesia'],
                'PH' => ['code' => 'PH', 'name' => 'Philippines'],
                'TH' => ['code' => 'TH', 'name' => 'Thailand'],
                'VN' => ['code' => 'VN', 'name' => 'Vietnam'],
                'JP' => ['code' => 'JP', 'name' => 'Japan'],
                'KR' => ['code' => 'KR', 'name' => 'South Korea'],
                'CN' => ['code' => 'CN', 'name' => 'China'],
                'TR' => ['code' => 'TR', 'name' => 'Turkey'],
                'ZA' => ['code' => 'ZA', 'name' => 'South Africa'],
                'BR' => ['code' => 'BR', 'name' => 'Brazil'],
                'MX' => ['code' => 'MX', 'name' => 'Mexico'],
                'AR' => ['code' => 'AR', 'name' => 'Argentina'],
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

    /**
     * Map a country (ISO alpha-2) to its primary currency code, for automatic
     * GeoIP price localization. Returns null when unknown — the caller then keeps
     * the store base currency. Only currencies we can format/convert are returned.
     */
    public static function currencyForCountry(?string $countryCode): ?string
    {
        if (! is_string($countryCode) || strlen($countryCode) !== 2) {
            return null;
        }

        static $map = [
            'US' => 'USD', 'GB' => 'GBP', 'IN' => 'INR', 'BD' => 'BDT', 'PK' => 'PKR',
            'NP' => 'NPR', 'LK' => 'LKR', 'CA' => 'CAD', 'AU' => 'AUD', 'NZ' => 'NZD',
            'SG' => 'SGD', 'MY' => 'MYR', 'ID' => 'IDR', 'PH' => 'PHP', 'TH' => 'THB',
            'VN' => 'VND', 'JP' => 'JPY', 'KR' => 'KRW', 'CN' => 'CNY', 'HK' => 'HKD',
            'AE' => 'AED', 'SA' => 'SAR', 'QA' => 'QAR', 'KW' => 'KWD', 'BH' => 'BHD',
            'OM' => 'OMR', 'TR' => 'TRY', 'ZA' => 'ZAR', 'NG' => 'NGN', 'KE' => 'KES',
            'EG' => 'EGP', 'MA' => 'MAD', 'BR' => 'BRL', 'MX' => 'MXN', 'AR' => 'ARS',
            'CL' => 'CLP', 'CO' => 'COP', 'PE' => 'PEN', 'CH' => 'CHF', 'SE' => 'SEK',
            'NO' => 'NOK', 'DK' => 'DKK', 'PL' => 'PLN', 'CZ' => 'CZK', 'HU' => 'HUF',
            'RO' => 'RON',
            // Eurozone
            'DE' => 'EUR', 'FR' => 'EUR', 'IT' => 'EUR', 'ES' => 'EUR', 'NL' => 'EUR',
            'BE' => 'EUR', 'AT' => 'EUR', 'IE' => 'EUR', 'PT' => 'EUR', 'FI' => 'EUR',
            'GR' => 'EUR', 'SK' => 'EUR', 'SI' => 'EUR', 'LT' => 'EUR', 'LV' => 'EUR',
            'EE' => 'EUR', 'LU' => 'EUR', 'CY' => 'EUR', 'MT' => 'EUR', 'HR' => 'EUR',
        ];

        $code = $map[strtoupper($countryCode)] ?? null;

        return $code && in_array($code, self::currencies(), true) ? $code : null;
    }

    public static function countryName(?string $countryCode, string $locale = 'en'): ?string
    {
        if (! is_string($countryCode) || strlen($countryCode) !== 2) {
            return null;
        }

        $countryCode = strtoupper($countryCode);

        if (extension_loaded('intl') && function_exists('locale_get_display_region')) {
            try {
                $name = locale_get_display_region('-'.$countryCode, $locale);
                if (is_string($name) && $name !== '') {
                    return $name;
                }
            } catch (\Throwable) {
                // Fall back
            }
        }

        // Fallback: search in our countries list
        foreach (self::countries($locale) as $country) {
            if ($country['code'] === $countryCode) {
                return $country['name'];
            }
        }

        return $countryCode;
    }

    public static function formatMoney(float|int|string|null $amount, string $currencyCode): string
    {
        $amount = (float) ($amount ?? 0);
        $currencyCode = strtoupper($currencyCode);
        $currency = self::currencyFormat($currencyCode);
        $number = self::formatNumber($amount, $currency['decimals']);

        return self::applyCurrencyPosition($number, $currency['symbol'], self::activeCurrencyPosition());
    }

    private static function formatNumber(float $amount, int $decimals): string
    {
        if (extension_loaded('intl') && class_exists(NumberFormatter::class)) {
            try {
                $formatter = new NumberFormatter(app()->getLocale() ?: 'en', NumberFormatter::DECIMAL);
                $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $decimals);
                $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
                $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
                $formatted = $formatter->format($amount);

                if (is_string($formatted)) {
                    return $formatted;
                }
            } catch (\Throwable) {
                // Fall back
            }
        }

        return number_format($amount, $decimals);
    }

    /**
     * @return array{symbol: string, decimals: int}
     */
    private static function currencyFormat(string $currencyCode): array
    {
        if (isset(self::$currencyFormats[$currencyCode])) {
            return self::$currencyFormats[$currencyCode];
        }

        $currency = Currency::query()
            ->where('code', $currencyCode)
            ->first(['symbol', 'decimal_places']);

        // Fallback chain for a currency with no DB row: static catalog → intl → code.
        $catalog = CurrencyCatalog::get($currencyCode);

        return self::$currencyFormats[$currencyCode] = [
            'symbol' => $currency?->symbol
                ?: ($catalog['symbol'] ?? self::intlCurrencySymbol($currencyCode)),
            'decimals' => (int) ($currency?->decimal_places
                ?? $catalog['decimals']
                ?? settings('currency_decimals', 2)),
        ];
    }

    private static function intlCurrencySymbol(string $currencyCode): string
    {
        if (extension_loaded('intl') && class_exists(NumberFormatter::class)) {
            try {
                $formatter = new NumberFormatter(app()->getLocale() ?: 'en', NumberFormatter::CURRENCY);
                $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currencyCode);
                $symbol = $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL);

                if (is_string($symbol) && $symbol !== '') {
                    return $symbol;
                }
            } catch (\Throwable) {
                // Fall back
            }
        }

        return $currencyCode;
    }

    private static function activeCurrencyPosition(): string
    {
        if (self::$currencyPosition !== null) {
            return self::$currencyPosition;
        }

        // Currency position is a global setting now (one base currency), no longer
        // stored per language.
        $position = settings('currency_position', self::CURRENCY_POSITION_BEFORE);

        return self::$currencyPosition = in_array($position, [
            self::CURRENCY_POSITION_BEFORE,
            self::CURRENCY_POSITION_BEFORE_WITH_SPACE,
            self::CURRENCY_POSITION_AFTER,
            self::CURRENCY_POSITION_AFTER_WITH_SPACE,
        ], true) ? $position : self::CURRENCY_POSITION_BEFORE;
    }

    private static function applyCurrencyPosition(string $number, string $symbol, string $position): string
    {
        return match ($position) {
            self::CURRENCY_POSITION_BEFORE_WITH_SPACE => "{$symbol} {$number}",
            self::CURRENCY_POSITION_AFTER => "{$number}{$symbol}",
            self::CURRENCY_POSITION_AFTER_WITH_SPACE => "{$number} {$symbol}",
            default => "{$symbol}{$number}",
        };
    }
}
