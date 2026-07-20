<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Services\CurrencyRateService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * `convert_currency()` treats Currency::exchange_rate as "units of this currency per 1
 * USD" — it divides by the source rate to reach USD. Providers do not all quote against
 * USD, so the sync must rebase before storing.
 */
class CurrencyRateSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([['USD', 1], ['EUR', 1], ['GBP', 1], ['INR', 1]] as [$code, $rate]) {
            Currency::create([
                'code' => $code, 'symbol' => $code, 'name' => $code,
                'exchange_rate' => $rate, 'is_default' => $code === 'USD', 'is_active' => true,
            ]);
        }
    }

    private function rateOf(string $code): float
    {
        return round((float) Currency::where('code', $code)->value('exchange_rate'), 4);
    }

    public function test_fixer_eur_based_rates_are_rebased_onto_usd(): void
    {
        // Fixer's free tier is EUR-based and names the field `base`, not `source`. The old
        // code read only `source`, defaulted to "USD", and stored these EUR-quoted rates
        // as if they were USD-quoted — putting every localized price out by the EUR/USD rate.
        Http::fake(['*' => Http::response([
            'base' => 'EUR',
            'rates' => ['USD' => 1.08, 'GBP' => 0.86, 'INR' => 90.0],
        ])]);

        $result = (new CurrencyRateService('fixer', 'key'))->syncRates();

        $this->assertTrue($result['success']);

        // 1 EUR = 1.08 USD, so 1 USD = 1/1.08 EUR = 0.9259 EUR.
        $this->assertSame(1.0, $this->rateOf('USD'));
        $this->assertSame(0.9259, $this->rateOf('EUR'));
        $this->assertSame(round(0.86 / 1.08, 4), $this->rateOf('GBP'));
        $this->assertSame(round(90.0 / 1.08, 4), $this->rateOf('INR'));
    }

    public function test_rebased_rates_round_trip_through_convert_currency(): void
    {
        Http::fake(['*' => Http::response([
            'base' => 'EUR',
            'rates' => ['USD' => 1.08, 'GBP' => 0.86],
        ])]);

        (new CurrencyRateService('fixer', 'key'))->syncRates();

        // The real-world assertion: $100 must convert to the correct number of GBP.
        // 100 USD × (0.86 GBP per EUR ÷ 1.08 USD per EUR) = 79.63 GBP.
        $this->assertSame(79.6296, round(convert_currency(100, 'USD', 'GBP'), 4));

        // And EUR: 100 USD ÷ 1.08 = 92.59 EUR.
        $this->assertSame(92.5926, round(convert_currency(100, 'USD', 'EUR'), 4));
    }

    public function test_exchangerate_host_usd_quotes_are_stored_as_is(): void
    {
        // ExchangeRate.host returns pair-keyed, USD-sourced quotes — already the shape
        // convert_currency() wants, so they must pass through untouched.
        Http::fake(['*' => Http::response([
            'source' => 'USD',
            'quotes' => ['USDEUR' => 0.92, 'USDGBP' => 0.79, 'USDINR' => 83.0],
        ])]);

        $result = (new CurrencyRateService('exchangerate', 'key'))->syncRates();

        $this->assertTrue($result['success']);
        $this->assertSame(1.0, $this->rateOf('USD'));
        $this->assertSame(0.92, $this->rateOf('EUR'));
        $this->assertSame(0.79, $this->rateOf('GBP'));
        $this->assertSame(83.0, $this->rateOf('INR'));
    }

    public function test_a_non_usd_payload_without_a_usd_rate_fails_loudly_instead_of_storing_garbage(): void
    {
        Http::fake(['*' => Http::response([
            'base' => 'EUR',
            'rates' => ['GBP' => 0.86],
        ])]);

        $result = (new CurrencyRateService('fixer', 'key'))->syncRates();

        $this->assertFalse($result['success']);
        // Rates are left untouched rather than being written with the wrong base.
        $this->assertSame(1.0, $this->rateOf('GBP'));
    }
}
