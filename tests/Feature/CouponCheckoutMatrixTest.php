<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckExtendedLicense;
use App\Http\Middleware\CheckPremium;
use App\Http\Middleware\LicenseMiddleware;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Coupon validation & application matrix — the full set of accept/reject rules
 * enforced server-side by CheckoutController::validCoupon()/discountedCycle(),
 * plus the discount maths in the Coupon model.
 *
 * Coupons are an Extended-License, admin-toggleable feature, so every case sets
 * license_type = 2 and coupons_enabled = true unless it is specifically testing
 * the disabled case.
 */
class CouponCheckoutMatrixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([CheckPremium::class, CheckExtendedLicense::class, LicenseMiddleware::class]);
        config(['broadcasting.default' => 'null']);

        Currency::create(['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'exchange_rate' => 1, 'is_default' => true, 'is_active' => true]);
        settings_set('default_currency', 'USD', 'string', 'general');
        settings_set('pricing_show_monthly', true, 'boolean', 'pricing');
        settings_set('pricing_show_yearly', true, 'boolean', 'pricing');
        settings_set('license_type', 2, 'integer', 'license');   // Extended — coupons allowed
        settings_set('coupons_enabled', true, 'boolean', 'features');
    }

    private function user(): User
    {
        return User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    }

    private function plan(float $monthly = 100): Plan
    {
        return Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => $monthly, 'price_yearly' => $monthly * 10,
            'vat_percentage' => 0, 'credits' => 1000,
            'is_active' => true, 'is_free' => false, 'sort_order' => 2,
        ]);
    }

    private function bankGateway(): PaymentGateway
    {
        return PaymentGateway::create([
            'slug' => 'bank_transfer', 'name' => 'Bank Transfer', 'is_enabled' => true,
            'processing_fee_type' => 'none', 'processing_fee_value' => 0, 'sort_order' => 1,
        ]);
    }

    private function coupon(array $attrs): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'SAVE'.strtoupper(substr(uniqid(), -6)),
            'type' => 'percent', 'value' => 10, 'is_active' => true,
        ], $attrs));
    }

    // ─── discount maths (model) ──────────────────

    public function test_percent_discount_is_capped_by_max_discount(): void
    {
        $coupon = $this->coupon(['type' => 'percent', 'value' => 50, 'max_discount' => 20]);
        // 50% of 100 = 50, but capped at 20.
        $this->assertSame(20.0, $coupon->calculateDiscount(100));
    }

    public function test_fixed_discount_never_exceeds_the_price(): void
    {
        $coupon = $this->coupon(['type' => 'fixed', 'value' => 30]);
        $this->assertSame(30.0, (float) $coupon->calculateDiscount(100));

        // A fixed discount larger than the price is clamped to the price.
        $big = $this->coupon(['type' => 'fixed', 'value' => 150]);
        $this->assertSame(80.0, (float) $big->calculateDiscount(80));
    }

    // ─── preview endpoint (accept) ───────────────

    public function test_preview_applies_a_percentage_discount(): void
    {
        $plan = $this->plan(100);
        $coupon = $this->coupon(['type' => 'percent', 'value' => 25]);
        $this->bankGateway();

        $this->actingAs($this->user())
            ->postJson('/checkout/coupon-preview', ['plan' => $plan->slug, 'billing' => 'monthly', 'coupon' => $coupon->code])
            ->assertOk()
            ->assertJsonPath('coupon.code', $coupon->code)
            ->assertJsonPath('summary.discount_amount', 25);
    }

    // ─── validation rejections ───────────────────

    public function test_a_coupon_locked_to_another_plan_is_rejected(): void
    {
        $plan = $this->plan();
        $otherPlan = $this->plan();
        $coupon = $this->coupon(['plan_id' => $otherPlan->id]);
        $gateway = $this->bankGateway();

        $this->actingAs($this->user())
            ->post('/checkout/session', ['plan' => $plan->slug, 'billing' => 'monthly', 'gateway' => $gateway->slug, 'coupon' => $coupon->code])
            ->assertStatus(422);
    }

    public function test_an_expired_coupon_is_rejected(): void
    {
        $plan = $this->plan();
        $coupon = $this->coupon(['expires_at' => now()->subDay()]);
        $gateway = $this->bankGateway();

        $this->actingAs($this->user())
            ->post('/checkout/session', ['plan' => $plan->slug, 'billing' => 'monthly', 'gateway' => $gateway->slug, 'coupon' => $coupon->code])
            ->assertStatus(422);
    }

    public function test_a_not_yet_started_coupon_is_rejected(): void
    {
        $plan = $this->plan();
        $coupon = $this->coupon(['starts_at' => now()->addWeek()]);
        $gateway = $this->bankGateway();

        $this->actingAs($this->user())
            ->post('/checkout/session', ['plan' => $plan->slug, 'billing' => 'monthly', 'gateway' => $gateway->slug, 'coupon' => $coupon->code])
            ->assertStatus(422);
    }

    public function test_a_globally_exhausted_coupon_is_rejected(): void
    {
        $plan = $this->plan();
        $coupon = $this->coupon(['max_uses' => 1, 'used_count' => 1]);
        $gateway = $this->bankGateway();

        $this->actingAs($this->user())
            ->post('/checkout/session', ['plan' => $plan->slug, 'billing' => 'monthly', 'gateway' => $gateway->slug, 'coupon' => $coupon->code])
            ->assertStatus(422);
    }

    public function test_a_coupon_at_its_per_user_limit_is_rejected(): void
    {
        $plan = $this->plan();
        $user = $this->user();
        $coupon = $this->coupon(['per_user_limit' => 1]);
        $gateway = $this->bankGateway();

        // A prior recorded redemption by this user.
        DB::table('coupon_redemptions')->insert([
            'coupon_id' => $coupon->id, 'user_id' => $user->id,
            'user_unique_guard' => $user->id, 'payment_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->post('/checkout/session', ['plan' => $plan->slug, 'billing' => 'monthly', 'gateway' => $gateway->slug, 'coupon' => $coupon->code])
            ->assertStatus(422);
    }

    public function test_an_unknown_coupon_code_is_rejected(): void
    {
        $plan = $this->plan();
        $gateway = $this->bankGateway();

        $this->actingAs($this->user())
            ->post('/checkout/session', ['plan' => $plan->slug, 'billing' => 'monthly', 'gateway' => $gateway->slug, 'coupon' => 'NOPE'])
            ->assertStatus(422);
    }

    // ─── coupon system disabled ──────────────────

    public function test_a_submitted_coupon_is_ignored_when_the_system_is_disabled(): void
    {
        settings_set('coupons_enabled', false, 'boolean', 'features');

        $plan = $this->plan(100);
        $coupon = $this->coupon(['type' => 'percent', 'value' => 50]);
        $this->bankGateway();

        // Even a real, valid coupon yields no discount when the feature is off.
        $this->actingAs($this->user())
            ->postJson('/checkout/coupon-preview', ['plan' => $plan->slug, 'billing' => 'monthly', 'coupon' => $coupon->code])
            ->assertOk()
            ->assertJsonPath('coupon', null)
            ->assertJsonPath('summary.discount_amount', 0);
    }

    // ─── 100%-off direct activation ──────────────

    public function test_a_100_percent_coupon_activates_the_plan_with_no_gateway_charge(): void
    {
        $plan = $this->plan(100);
        $user = $this->user();
        $coupon = $this->coupon(['type' => 'percent', 'value' => 100]);
        $gateway = $this->bankGateway();

        $this->actingAs($user)
            ->post('/checkout/session', ['plan' => $plan->slug, 'billing' => 'monthly', 'gateway' => $gateway->slug, 'coupon' => $coupon->code])
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHas('success');

        // Plan granted, payment completed at $0, one global coupon use recorded.
        $this->assertSame($plan->id, $user->fresh()->plan_id);
        $this->assertDatabaseHas('billing_subscriptions', ['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active']);
        $this->assertDatabaseHas('payments', ['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'completed', 'amount' => 0]);
        $this->assertSame(1, (int) $coupon->fresh()->used_count);
    }

    public function test_a_100_percent_coupon_cannot_be_redeemed_twice_by_the_same_user(): void
    {
        $plan = $this->plan(100);
        $user = $this->user();
        $coupon = $this->coupon(['type' => 'percent', 'value' => 100, 'per_user_limit' => 1]);
        $gateway = $this->bankGateway();

        $post = fn () => $this->actingAs($user)->post('/checkout/session', [
            'plan' => $plan->slug, 'billing' => 'monthly', 'gateway' => $gateway->slug, 'coupon' => $coupon->code,
        ]);

        $post()->assertSessionHas('success');
        // Second attempt is blocked by the recorded redemption.
        $post()->assertStatus(422);

        $this->assertSame(1, Payment::where('user_id', $user->id)->where('status', 'completed')->count());
    }
}
