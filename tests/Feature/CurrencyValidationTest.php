<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPremium;
use App\Http\Middleware\LicenseMiddleware;
use App\Models\Admin;
use App\Models\Currency;
use App\Models\Plan;
use App\Support\CountryCatalog;
use App\Support\CurrencyCatalog;
use Tests\TestCase;

/**
 * Currency codes are validated against CurrencyCatalog — the list the store can actually
 * render (symbol, decimals, symbol position) — rather than the `currencies` table, which
 * only seeds four rows.
 */
class CurrencyValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Admin premium/settings routes sit behind the license + premium gates.
        $this->withoutMiddleware([CheckPremium::class, LicenseMiddleware::class]);

        Currency::create(['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'exchange_rate' => 1, 'is_default' => true, 'is_active' => true]);
        settings_set('default_currency', 'USD', 'string', 'general');
    }

    private function plan(): Plan
    {
        return Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 10, 'price_yearly' => 100, 'credits' => 1000,
            'is_active' => true, 'is_free' => false, 'sort_order' => 1, 'vat_percentage' => 0,
        ]);
    }

    private function countryPricePayload(Plan $plan, string $currency): array
    {
        return [
            'name' => $plan->name,
            'price_monthly' => 10,
            'price_yearly' => 100,
            'credits' => 1000,
            'country_prices' => [[
                'country_code' => 'IN',
                'currency_code' => $currency,
                'price_monthly' => 500,
            ]],
        ];
    }

    // ─── The catalog is the capability boundary ───

    public function test_the_plans_currency_picker_offers_everything_the_store_can_render(): void
    {
        $response = $this->actingAsAdmin()->get(route('admin.plans.index'));

        $response->assertOk();
        $offered = $response->viewData('page')['props']['currencies'];

        // The picker used a separate hand-kept list that was a strict SUBSET of what the
        // store can actually format, so a dozen supported currencies — TWD, ILS, RUB, UAH…
        // — could never be selected. It now shares one source of truth with the validator.
        $this->assertSame(CurrencyCatalog::codes(), $offered);
        $this->assertContains('TWD', $offered);
        $this->assertNotContains('XYZ', $offered);
    }

    public function test_every_offerable_currency_can_actually_be_formatted(): void
    {
        // The guarantee the validation rule leans on: anything we accept, we can render.
        foreach (CurrencyCatalog::codes() as $code) {
            $this->assertNotSame('', CountryCatalog::formatMoney(10, $code), "Cannot format {$code}");
        }
    }

    // ─── Country prices ───

    private function actingAsAdmin(): self
    {
        $role = \App\Models\AdminRole::create(['name' => 'Super Admin', 'slug' => 'super-admin', 'is_system' => true]);
        config(['auth.providers.admins.super_admin_slug' => 'super-admin']);

        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        return $this->actingAs($admin, 'admin');
    }

    public function test_a_country_price_in_an_unknown_currency_is_rejected(): void
    {
        $plan = $this->plan();

        $this->actingAsAdmin()
            ->put(route('admin.plans.update', $plan), $this->countryPricePayload($plan, 'XYZ'))
            ->assertSessionHasErrors('country_prices.0.currency_code');

        // `size:3` alone let this through, producing a price the store could not format and
        // a gateway would reject — with nothing to say so until a customer hit checkout.
        $this->assertDatabaseCount('plan_country_prices', 0);
    }

    public function test_a_country_price_in_a_supported_currency_is_accepted(): void
    {
        $plan = $this->plan();

        // INR has no row in the four-currency `currencies` table, so an exists() rule would
        // have wrongly rejected this perfectly valid country price.
        $this->actingAsAdmin()
            ->put(route('admin.plans.update', $plan), $this->countryPricePayload($plan, 'INR'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('plan_country_prices', [
            'plan_id' => $plan->id,
            'country_code' => 'IN',
            'currency_code' => 'INR',
        ]);
    }

    // ─── Base (store) currency ───

    private function generalSettingsPayload(string $currency): array
    {
        return [
            'site_name' => 'MakeAI',
            'default_currency' => $currency,
            'currency_symbol' => '₹',
            'currency_position' => 'before',
            'currency_decimals' => 2,
            'app_timezone' => 'UTC',
        ];
    }

    public function test_a_base_currency_without_a_seeded_row_can_be_selected(): void
    {
        // The picker offered all 58 catalog currencies, but the rule demanded a row in the
        // `currencies` table — which seeds only four. Choosing INR (or JPY, or BRL) as the
        // store's base currency therefore failed validation, and the controller code written
        // to create the missing row could never run. The store was locked to USD/EUR/GBP/BDT.
        $this->actingAsAdmin()
            ->post(route('admin.settings.update'), $this->generalSettingsPayload('INR'))
            ->assertSessionHasNoErrors();

        $this->assertSame('INR', base_currency());

        // The row is created from catalog metadata and becomes the sole default.
        $this->assertDatabaseHas('currencies', ['code' => 'INR', 'is_default' => true]);
        $this->assertDatabaseHas('currencies', ['code' => 'USD', 'is_default' => false]);
    }

    public function test_an_unknown_base_currency_is_still_rejected(): void
    {
        $this->actingAsAdmin()
            ->post(route('admin.settings.update'), $this->generalSettingsPayload('XYZ'))
            ->assertSessionHasErrors('default_currency');

        $this->assertSame('USD', base_currency());
    }
}
