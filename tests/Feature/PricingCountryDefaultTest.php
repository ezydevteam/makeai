<?php

namespace Tests\Feature;

use App\Services\Pricing\PricingCountryDetector;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * An undetected visitor must fall back to the store's BASE-currency country, so
 * auto-localization never converts a non-USD store's own prices into another
 * currency by default. Genuinely-detected visitors are unaffected.
 */
class PricingCountryDefaultTest extends TestCase
{
    private function detect(): string
    {
        // A request with an empty session: no trusted-proxy Cloudflare header,
        // no stored pricing_country.
        $request = Request::create('/pricing', 'GET');
        $request->setLaravelSession($this->app['session']->driver('array'));

        return (new PricingCountryDetector)->detect($request);
    }

    public function test_falls_back_to_base_currency_country_when_no_signal(): void
    {
        settings_set('default_currency', 'BDT', 'string', 'general');
        settings_set('default_pricing_country', '', 'string', 'pricing'); // no explicit override

        // BDT → Bangladesh, so the default view shows the base currency.
        $this->assertSame('BD', $this->detect());
    }

    public function test_explicit_default_pricing_country_still_wins(): void
    {
        settings_set('default_currency', 'BDT', 'string', 'general');
        settings_set('default_pricing_country', 'GB', 'string', 'pricing');

        $this->assertSame('GB', $this->detect());
    }

    public function test_usd_store_defaults_to_us(): void
    {
        settings_set('default_currency', 'USD', 'string', 'general');
        settings_set('default_pricing_country', '', 'string', 'pricing');

        $this->assertSame('US', $this->detect());
    }
}
