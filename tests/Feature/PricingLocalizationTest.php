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

    private function vatPlan(): Plan
    {
        return Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 10, 'price_yearly' => 100,
            'original_price_monthly' => 20,
            'vat_percentage' => 20,
            'credits' => 1000, 'is_active' => true, 'is_free' => false, 'sort_order' => 1,
        ]);
    }

    public function test_the_localized_headline_price_excludes_vat_like_the_base_one(): void
    {
        $cycle = (new PlanPriceResolver)->resolve($this->vatPlan(), 'IN')['monthly'];

        // The headline is the ex-VAT SUBTOTAL. The card used to fall back to
        // display_amount (the VAT-INCLUSIVE total) whenever it was localized, so an Indian
        // visitor saw ₹960 where a US visitor saw $10 — 20% more for the same plan.
        $this->assertSame(10.0, $cycle['subtotal_amount']);      // base, ex-VAT
        $this->assertSame(800.0, $cycle['display_subtotal_amount']); // 10 × 80, ex-VAT
        $this->assertSame(960.0, $cycle['display_amount']);      // 12 × 80, VAT-inclusive total
    }

    public function test_every_localized_figure_on_the_card_is_in_the_local_currency(): void
    {
        $cycle = (new PlanPriceResolver)->resolve($this->vatPlan(), 'IN')['monthly'];

        // A localized card must not pair a rupee headline with a dollar VAT line or a
        // dollar strikethrough.
        $this->assertStringContainsString('160', $cycle['display_vat_formatted']);      // 2 × 80
        $this->assertStringContainsString('1,600', $cycle['display_original_formatted']); // 20 × 80
    }

    public function test_a_trial_advertises_the_real_renewal_price_not_zero(): void
    {
        $plan = $this->vatPlan();
        $plan->update(['trial_all_countries' => true, 'trial_days' => 14]);

        $cycle = (new PlanPriceResolver)->resolve($plan, 'US')['monthly'];

        // `amount` is zeroed for a trial, and the card rendered it as "renews at $0.00".
        $this->assertSame(0.0, $cycle['amount']);
        $this->assertSame(12.0, $cycle['renewal_amount']); // 10 + 20% VAT
        $this->assertStringContainsString('12', $cycle['renewal_formatted']);
    }
}
