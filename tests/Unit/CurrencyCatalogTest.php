<?php

namespace Tests\Unit;

use App\Support\CountryCatalog;
use App\Support\CurrencyCatalog;
use Tests\TestCase;

/**
 * CurrencyCatalog — static reference data for the currency picker and the
 * formatting fallback when a currency has no `currencies` DB row.
 */
class CurrencyCatalogTest extends TestCase
{
    public function test_offers_many_currencies_not_just_a_handful(): void
    {
        // The picker was previously limited to the few DB rows; the catalog must be broad.
        $this->assertGreaterThan(40, count(CurrencyCatalog::codes()));
    }

    public function test_lookup_fields_for_a_known_currency(): void
    {
        $this->assertSame('Indian Rupee', CurrencyCatalog::name('INR'));
        $this->assertSame('₹', CurrencyCatalog::symbol('INR'));
        $this->assertSame(2, CurrencyCatalog::decimals('INR'));
        $this->assertSame('IN', CurrencyCatalog::country('INR'));
    }

    public function test_zero_decimal_currencies_are_marked_correctly(): void
    {
        $this->assertSame(0, CurrencyCatalog::decimals('JPY'));
        $this->assertSame(0, CurrencyCatalog::decimals('KRW'));
        // Kuwaiti dinar uses three decimals.
        $this->assertSame(3, CurrencyCatalog::decimals('KWD'));
    }

    public function test_case_insensitive_and_unknown_safe(): void
    {
        $this->assertTrue(CurrencyCatalog::has('eur'));
        $this->assertFalse(CurrencyCatalog::has('ZZZ'));
        $this->assertNull(CurrencyCatalog::get('ZZZ'));
        // Defaults for an unknown code.
        $this->assertSame(2, CurrencyCatalog::decimals('ZZZ'));
        $this->assertSame('before', CurrencyCatalog::position('ZZZ'));
    }

    public function test_options_are_select_ready(): void
    {
        $options = CurrencyCatalog::options();
        $this->assertArrayHasKey('code', $options[0]);
        $this->assertArrayHasKey('name', $options[0]);
        $this->assertArrayHasKey('symbol', $options[0]);
        // Position + decimals are included so the admin UI can auto-fill on change.
        $this->assertArrayHasKey('position', $options[0]);
        $this->assertArrayHasKey('decimals', $options[0]);
    }

    public function test_formatting_falls_back_to_catalog_without_db_row(): void
    {
        // No currencies table row for JPY — formatter must still use catalog symbol
        // and 0 decimals (not the generic 2-decimal default).
        $this->assertSame('¥1,235', CountryCatalog::formatMoney(1234.56, 'JPY'));
    }
}
