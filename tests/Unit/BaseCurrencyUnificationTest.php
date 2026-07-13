<?php

namespace Tests\Unit;

use App\Models\Currency;
use Tests\TestCase;

/**
 * Phase 1 — currency has ONE source of truth (default_currency). base_currency(),
 * Currency::getDefault(), and the credit derivation all follow it, so the old
 * three-way drift (default_currency vs pricing_currency_code vs is_default) is gone.
 */
class BaseCurrencyUnificationTest extends TestCase
{
    public function test_base_currency_follows_default_currency_setting(): void
    {
        settings_set('default_currency', 'EUR', 'string', 'general');

        $this->assertSame('EUR', base_currency());
    }

    public function test_base_currency_falls_back_to_pricing_then_usd(): void
    {
        // Blank default → fall back to the legacy pricing key.
        settings_set('default_currency', '', 'string', 'general');
        settings_set('pricing_currency_code', 'GBP', 'string', 'pricing');
        $this->assertSame('GBP', base_currency());

        // Blank both → USD.
        settings_set('pricing_currency_code', '', 'string', 'pricing');
        $this->assertSame('USD', base_currency());
    }

    public function test_get_default_follows_base_currency_not_stale_flag(): void
    {
        // is_default still points at USD, but the store base is EUR — getDefault must
        // follow the setting, not the stale flag.
        Currency::create(['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'exchange_rate' => 1, 'is_default' => true, 'is_active' => true]);
        Currency::create(['code' => 'EUR', 'symbol' => '€', 'name' => 'Euro', 'exchange_rate' => 0.9, 'is_default' => false, 'is_active' => true]);

        settings_set('default_currency', 'EUR', 'string', 'general');

        $this->assertSame('EUR', Currency::getDefault()?->code);
    }
}
