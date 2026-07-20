<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPremium;
use App\Http\Middleware\LicenseMiddleware;
use App\Models\GatewaySubscription;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use Tests\TestCase;

/**
 * Finding #1 — a recurring, in-place-capable upgrade must be routed to the
 * billing page (gateway swaps the plan and prorates itself), NOT charged the
 * full recurring price through checkout as a second subscription.
 *
 * Uses PayPal for the recurring subscription: supportsInPlace() only needs an
 * enabled PayPal gateway with credentials, so the test is self-contained (no
 * Stripe/Cashier config required).
 */
class RecurringUpgradeRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Bypass the premium + license gates on the checkout route (this test
        // exercises the plan-change routing, not licensing).
        $this->withoutMiddleware([CheckPremium::class, LicenseMiddleware::class]);
    }

    private function plan(string $slug, int $sort, float $monthly): Plan
    {
        return Plan::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'price_monthly' => $monthly,
            'price_yearly' => $monthly * 10,
            'vat_percentage' => 0,
            'credits' => 1000,
            'is_active' => true,
            'is_free' => false,
            'sort_order' => $sort,
        ]);
    }

    public function test_recurring_upgrade_is_redirected_to_billing_and_creates_no_payment(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $basic = $this->plan('basic', 1, 10);
        $pro = $this->plan('pro', 2, 30);

        // Enabled PayPal gateway with credentials → supportsInPlace() is true.
        PaymentGateway::create([
            'slug' => 'paypal',
            'name' => 'PayPal',
            'is_enabled' => true,
            'credentials' => PaymentGateway::encryptCredentials([
                'client_id' => 'test-client-id',
                'client_secret' => 'test-client-secret',
            ]),
        ]);

        GatewaySubscription::create([
            'user_id' => $user->id,
            'plan_id' => $basic->id,
            'billing_cycle' => 'monthly',
            'status' => GatewaySubscription::STATUS_ACTIVE,
            'gateway' => 'paypal',
            'gateway_subscription_id' => 'I-SUB123',
            'amount' => 10,
            'currency' => 'USD',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $response = $this->actingAs($user)->post(route('checkout.session'), [
            'plan' => 'pro',
            'billing' => 'monthly',
            'gateway' => 'paypal',
        ]);

        $response->assertRedirect(route('user.dashboard.billing'));
        $this->assertDatabaseMissing('payments', ['user_id' => $user->id, 'plan_id' => $pro->id]);
    }
}
