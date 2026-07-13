<?php

declare(strict_types=1);

namespace App\Support;

/**
 * CurrencyCatalog — static reference data for popular world currencies.
 *
 * One place that maps a currency code → display name, symbol, default symbol
 * position, decimal places, and a representative country (ISO alpha-2, for flags).
 * Used to populate the currency picker in Admin → Settings and as the formatting
 * fallback for currencies that don't (yet) have a row in the `currencies` table.
 *
 * Symbol position uses the app's four canonical values:
 *   before | before_with_space | after | after_with_space
 */
final class CurrencyCatalog
{
    /**
     * code => [name, symbol, position, decimals, country]
     *
     * @var array<string, array{name: string, symbol: string, position: string, decimals: int, country: string}>
     */
    private const CURRENCIES = [
        // ── Majors ────────────────────────────────────────────────
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'position' => 'before', 'decimals' => 2, 'country' => 'US'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'position' => 'before', 'decimals' => 2, 'country' => 'EU'],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'position' => 'before', 'decimals' => 2, 'country' => 'GB'],
        'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥', 'position' => 'before', 'decimals' => 0, 'country' => 'JP'],
        'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥', 'position' => 'before', 'decimals' => 2, 'country' => 'CN'],
        'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'CHF', 'position' => 'before', 'decimals' => 2, 'country' => 'CH'],
        'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$', 'position' => 'before', 'decimals' => 2, 'country' => 'CA'],
        'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'position' => 'before', 'decimals' => 2, 'country' => 'AU'],
        'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'position' => 'before', 'decimals' => 2, 'country' => 'NZ'],

        // ── Asia ──────────────────────────────────────────────────
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹', 'position' => 'before', 'decimals' => 2, 'country' => 'IN'],
        'HKD' => ['name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'position' => 'before', 'decimals' => 2, 'country' => 'HK'],
        'SGD' => ['name' => 'Singapore Dollar', 'symbol' => 'S$', 'position' => 'before', 'decimals' => 2, 'country' => 'SG'],
        'KRW' => ['name' => 'South Korean Won', 'symbol' => '₩', 'position' => 'before', 'decimals' => 0, 'country' => 'KR'],
        'TWD' => ['name' => 'Taiwan Dollar', 'symbol' => 'NT$', 'position' => 'before', 'decimals' => 2, 'country' => 'TW'],
        'THB' => ['name' => 'Thai Baht', 'symbol' => '฿', 'position' => 'before', 'decimals' => 2, 'country' => 'TH'],
        'MYR' => ['name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'position' => 'before', 'decimals' => 2, 'country' => 'MY'],
        'IDR' => ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'position' => 'before', 'decimals' => 0, 'country' => 'ID'],
        'PHP' => ['name' => 'Philippine Peso', 'symbol' => '₱', 'position' => 'before', 'decimals' => 2, 'country' => 'PH'],
        'VND' => ['name' => 'Vietnamese Dong', 'symbol' => '₫', 'position' => 'after_with_space', 'decimals' => 0, 'country' => 'VN'],
        'PKR' => ['name' => 'Pakistani Rupee', 'symbol' => '₨', 'position' => 'before', 'decimals' => 2, 'country' => 'PK'],
        'BDT' => ['name' => 'Bangladeshi Taka', 'symbol' => '৳', 'position' => 'before', 'decimals' => 2, 'country' => 'BD'],
        'LKR' => ['name' => 'Sri Lankan Rupee', 'symbol' => '₨', 'position' => 'before', 'decimals' => 2, 'country' => 'LK'],
        'NPR' => ['name' => 'Nepalese Rupee', 'symbol' => '₨', 'position' => 'before', 'decimals' => 2, 'country' => 'NP'],

        // ── Middle East ───────────────────────────────────────────
        'AED' => ['name' => 'UAE Dirham', 'symbol' => 'د.إ', 'position' => 'before', 'decimals' => 2, 'country' => 'AE'],
        'SAR' => ['name' => 'Saudi Riyal', 'symbol' => '﷼', 'position' => 'before', 'decimals' => 2, 'country' => 'SA'],
        'QAR' => ['name' => 'Qatari Riyal', 'symbol' => '﷼', 'position' => 'before', 'decimals' => 2, 'country' => 'QA'],
        'KWD' => ['name' => 'Kuwaiti Dinar', 'symbol' => 'د.ك', 'position' => 'before', 'decimals' => 3, 'country' => 'KW'],
        'BHD' => ['name' => 'Bahraini Dinar', 'symbol' => '.د.ب', 'position' => 'before', 'decimals' => 3, 'country' => 'BH'],
        'OMR' => ['name' => 'Omani Rial', 'symbol' => '﷼', 'position' => 'before', 'decimals' => 3, 'country' => 'OM'],
        'JOD' => ['name' => 'Jordanian Dinar', 'symbol' => 'د.ا', 'position' => 'before', 'decimals' => 3, 'country' => 'JO'],
        'ILS' => ['name' => 'Israeli New Shekel', 'symbol' => '₪', 'position' => 'before', 'decimals' => 2, 'country' => 'IL'],
        'TRY' => ['name' => 'Turkish Lira', 'symbol' => '₺', 'position' => 'before', 'decimals' => 2, 'country' => 'TR'],

        // ── Africa ────────────────────────────────────────────────
        'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R', 'position' => 'before', 'decimals' => 2, 'country' => 'ZA'],
        'NGN' => ['name' => 'Nigerian Naira', 'symbol' => '₦', 'position' => 'before', 'decimals' => 2, 'country' => 'NG'],
        'EGP' => ['name' => 'Egyptian Pound', 'symbol' => 'E£', 'position' => 'before', 'decimals' => 2, 'country' => 'EG'],
        'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'position' => 'before', 'decimals' => 2, 'country' => 'KE'],
        'GHS' => ['name' => 'Ghanaian Cedi', 'symbol' => '₵', 'position' => 'before', 'decimals' => 2, 'country' => 'GH'],
        'MAD' => ['name' => 'Moroccan Dirham', 'symbol' => 'د.م.', 'position' => 'before', 'decimals' => 2, 'country' => 'MA'],
        'TND' => ['name' => 'Tunisian Dinar', 'symbol' => 'د.ت', 'position' => 'before', 'decimals' => 3, 'country' => 'TN'],
        'DZD' => ['name' => 'Algerian Dinar', 'symbol' => 'د.ج', 'position' => 'before', 'decimals' => 2, 'country' => 'DZ'],

        // ── Europe (non-euro) ─────────────────────────────────────
        'SEK' => ['name' => 'Swedish Krona', 'symbol' => 'kr', 'position' => 'after_with_space', 'decimals' => 2, 'country' => 'SE'],
        'NOK' => ['name' => 'Norwegian Krone', 'symbol' => 'kr', 'position' => 'after_with_space', 'decimals' => 2, 'country' => 'NO'],
        'DKK' => ['name' => 'Danish Krone', 'symbol' => 'kr', 'position' => 'after_with_space', 'decimals' => 2, 'country' => 'DK'],
        'PLN' => ['name' => 'Polish Zloty', 'symbol' => 'zł', 'position' => 'after_with_space', 'decimals' => 2, 'country' => 'PL'],
        'CZK' => ['name' => 'Czech Koruna', 'symbol' => 'Kč', 'position' => 'after_with_space', 'decimals' => 2, 'country' => 'CZ'],
        'HUF' => ['name' => 'Hungarian Forint', 'symbol' => 'Ft', 'position' => 'after_with_space', 'decimals' => 0, 'country' => 'HU'],
        'RON' => ['name' => 'Romanian Leu', 'symbol' => 'lei', 'position' => 'after_with_space', 'decimals' => 2, 'country' => 'RO'],
        'BGN' => ['name' => 'Bulgarian Lev', 'symbol' => 'лв', 'position' => 'after_with_space', 'decimals' => 2, 'country' => 'BG'],
        'UAH' => ['name' => 'Ukrainian Hryvnia', 'symbol' => '₴', 'position' => 'before', 'decimals' => 2, 'country' => 'UA'],
        'RUB' => ['name' => 'Russian Ruble', 'symbol' => '₽', 'position' => 'after_with_space', 'decimals' => 2, 'country' => 'RU'],
        'ISK' => ['name' => 'Icelandic Krona', 'symbol' => 'kr', 'position' => 'after_with_space', 'decimals' => 0, 'country' => 'IS'],

        // ── Americas ──────────────────────────────────────────────
        'MXN' => ['name' => 'Mexican Peso', 'symbol' => '$', 'position' => 'before', 'decimals' => 2, 'country' => 'MX'],
        'BRL' => ['name' => 'Brazilian Real', 'symbol' => 'R$', 'position' => 'before', 'decimals' => 2, 'country' => 'BR'],
        'ARS' => ['name' => 'Argentine Peso', 'symbol' => '$', 'position' => 'before', 'decimals' => 2, 'country' => 'AR'],
        'CLP' => ['name' => 'Chilean Peso', 'symbol' => '$', 'position' => 'before', 'decimals' => 0, 'country' => 'CL'],
        'COP' => ['name' => 'Colombian Peso', 'symbol' => '$', 'position' => 'before', 'decimals' => 2, 'country' => 'CO'],
        'PEN' => ['name' => 'Peruvian Sol', 'symbol' => 'S/', 'position' => 'before', 'decimals' => 2, 'country' => 'PE'],
        'UYU' => ['name' => 'Uruguayan Peso', 'symbol' => '$U', 'position' => 'before', 'decimals' => 2, 'country' => 'UY'],
    ];

    private const DEFAULT_POSITION = 'before';

    private const DEFAULT_DECIMALS = 2;

    /**
     * All currencies keyed by code.
     *
     * @return array<string, array{name: string, symbol: string, position: string, decimals: int, country: string}>
     */
    public static function all(): array
    {
        return self::CURRENCIES;
    }

    /**
     * All supported currency codes.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::CURRENCIES);
    }

    public static function has(string $code): bool
    {
        return isset(self::CURRENCIES[strtoupper($code)]);
    }

    /**
     * Full record for a currency, or null if unknown.
     *
     * @return array{name: string, symbol: string, position: string, decimals: int, country: string}|null
     */
    public static function get(string $code): ?array
    {
        return self::CURRENCIES[strtoupper($code)] ?? null;
    }

    public static function name(string $code): ?string
    {
        return self::get($code)['name'] ?? null;
    }

    public static function symbol(string $code): ?string
    {
        return self::get($code)['symbol'] ?? null;
    }

    public static function decimals(string $code): int
    {
        return self::get($code)['decimals'] ?? self::DEFAULT_DECIMALS;
    }

    public static function position(string $code): string
    {
        return self::get($code)['position'] ?? self::DEFAULT_POSITION;
    }

    public static function country(string $code): ?string
    {
        return self::get($code)['country'] ?? null;
    }

    /**
     * Options for a currency <select>, including the metadata needed to auto-fill
     * the symbol / position / decimals fields when the admin changes currency.
     *
     * @return list<array{code: string, name: string, symbol: string, position: string, decimals: int}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::CURRENCIES as $code => $meta) {
            $options[] = [
                'code' => $code,
                'name' => $meta['name'],
                'symbol' => $meta['symbol'],
                'position' => $meta['position'],
                'decimals' => $meta['decimals'],
            ];
        }

        return $options;
    }
}
