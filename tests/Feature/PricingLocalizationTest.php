<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Plan;
use App\Services\Pricing\PlanPriceResolver;
use Tests\TestCase;

/**
 * Phase 3 — GeoIP price localization is DISPLAY-only. The resolver localizes the
 * shown amount to the visitor's currency, but the charge (`amount`/`currency_code`)
 * stays in the base currency, per the confirmed "charge base unless per-country" rule.
 */
class PricingLocalizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Currency::create(['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'exchange_rate' => 1, 'is_default' => true, 'is_active' => true]);
        Currency::create(['code' => 'INR', 'symbol' => '₹', 'name' => 'Indian Rupee', 'exchange_rate' => 80, 'is_default' => false, 'is_active' => true]);
        settings_set('default_currency', 'USD', 'string', 'general');
        settings_set('pricing_auto_localize', true, 'boolean', 'pricing');
    }

    private function plan(): Plan
    {
        return Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 10, 'price_yearly' => 100,
            'vat_percentage' => 0,
            'credits' => 1000, 'is_active' => true, 'is_free' => false, 'sort_order' => 1,
        ]);
    }

    public function test_localizes_display_but_charges_base(): void
    {
        $resolved = (new PlanPriceResolver)->resolve($this->plan(), 'IN');

        // Charge stays base (USD 10) — this is what checkout bills.
        $this->assertSame('USD', $resolved['currency_code']);
        $this->assertSame(10.0, $resolved['monthly']['amount']);

        // Display is localized to INR (10 × 80 = 800).
        $this->assertTrue($resolved['is_localized']);
        $this->assertSame('INR', $resolved['display_currency_code']);
        $this->assertSame(800.0, $resolved['monthly']['display_amount']);
    }

    public function test_home_country_is_not_localized(): void
    {
        $resolved = (new PlanPriceResolver)->resolve($this->plan(), 'US');

        $this->assertFalse($resolved['is_localized']);
        $this->assertSame('USD', $resolved['display_currency_code']);
        $this->assertSame($resolved['monthly']['amount'], $resolved['monthly']['display_amount']);
    }

    public function test_toggle_off_disables_localization(): void
    {
        settings_set('pricing_auto_localize', false, 'boolean', 'pricing');

        $resolved = (new PlanPriceResolver)->resolve($this->plan(), 'IN');

        $this->assertFalse($resolved['is_localized']);
        $this->assertSame('USD', $resolved['display_currency_code']);
        $this->assertSame(10.0, $resolved['monthly']['display_amount']);
    }
}
