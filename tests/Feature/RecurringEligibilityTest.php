<?php

namespace Tests\Feature;

use App\Http\Controllers\CheckoutController;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The recurring/one-time decision. A real gateway subscription (Stripe / PayPal)
 * bills a FIXED price, so it cannot carry a coupon, a country-specific price, an
 * upgrade proration credit, or a lifetime (one-time) cycle — every one of those
 * forces a one-time charge instead. Only Stripe and PayPal have recurring plan
 * ids at all; all other gateways are always one-time.
 *
 * willBeRecurring() (raw inputs, at checkout) and isRecurringEligible() (read back
 * off the stored payment) MUST agree, or a charge is priced as one thing and taken
 * as another — these lock both down.
 */
class RecurringEligibilityTest extends TestCase
{
    private function controller(): CheckoutController
    {
        return app(CheckoutController::class);
    }

    private function willBeRecurring(PaymentGateway $gateway, Plan $plan, string $billing, ?Coupon $coupon, string $source, float $proration): bool
    {
        $m = new ReflectionMethod(CheckoutController::class, 'willBeRecurring');

        return $m->invoke($this->controller(), $gateway, $plan, $billing, $coupon, $source, $proration);
    }

    private function isRecurringEligible(Payment $payment): bool
    {
        $m = new ReflectionMethod(CheckoutController::class, 'isRecurringEligible');

        return $m->invoke($this->controller(), $payment);
    }

    private function stripeGateway(): PaymentGateway
    {
        return PaymentGateway::create(['slug' => 'stripe', 'name' => 'Stripe', 'is_enabled' => true, 'sort_order' => 1]);
    }

    private function paypalGateway(): PaymentGateway
    {
        return PaymentGateway::create(['slug' => 'paypal', 'name' => 'PayPal', 'is_enabled' => true, 'sort_order' => 2]);
    }

    private function oneTimeGateway(): PaymentGateway
    {
        return PaymentGateway::create(['slug' => 'razorpay', 'name' => 'Razorpay', 'is_enabled' => true, 'sort_order' => 3]);
    }

    private function recurringPlan(): Plan
    {
        return Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 10, 'price_yearly' => 100, 'price_lifetime' => 300,
            'vat_percentage' => 0, 'credits' => 1000,
            'is_active' => true, 'is_free' => false, 'sort_order' => 2,
            'stripe_price_monthly_id' => 'price_m', 'stripe_price_yearly_id' => 'price_y',
            'paypal_plan_monthly_id' => 'P-mon', 'paypal_plan_yearly_id' => 'P-year',
        ]);
    }

    private function coupon(): Coupon
    {
        return Coupon::create(['code' => 'SAVE10', 'type' => 'percent', 'value' => 10, 'is_active' => true]);
    }

    // ─── accepted: clean recurring purchases ─────

    public function test_stripe_monthly_with_a_price_id_is_recurring(): void
    {
        $this->assertTrue($this->willBeRecurring($this->stripeGateway(), $this->recurringPlan(), 'monthly', null, 'default', 0.0));
    }

    public function test_paypal_yearly_with_a_plan_id_is_recurring(): void
    {
        $this->assertTrue($this->willBeRecurring($this->paypalGateway(), $this->recurringPlan(), 'yearly', null, 'default', 0.0));
    }

    // ─── rejected: forces a one-time charge ──────

    public function test_a_coupon_forces_a_one_time_charge(): void
    {
        $this->assertFalse($this->willBeRecurring($this->stripeGateway(), $this->recurringPlan(), 'monthly', $this->coupon(), 'default', 0.0));
    }

    public function test_a_country_specific_price_forces_a_one_time_charge(): void
    {
        // Any pricing source other than 'default' is a localized/country price.
        $this->assertFalse($this->willBeRecurring($this->stripeGateway(), $this->recurringPlan(), 'monthly', null, 'country', 0.0));
    }

    public function test_an_upgrade_proration_credit_forces_a_one_time_charge(): void
    {
        $this->assertFalse($this->willBeRecurring($this->stripeGateway(), $this->recurringPlan(), 'monthly', null, 'default', 5.0));
    }

    public function test_lifetime_is_never_recurring(): void
    {
        $this->assertFalse($this->willBeRecurring($this->stripeGateway(), $this->recurringPlan(), 'lifetime', null, 'default', 0.0));
    }

    public function test_a_non_stripe_paypal_gateway_is_never_recurring(): void
    {
        // Razorpay has no recurring plan id mapping — always one-time, even clean.
        $this->assertFalse($this->willBeRecurring($this->oneTimeGateway(), $this->recurringPlan(), 'monthly', null, 'default', 0.0));
    }

    public function test_a_plan_without_a_gateway_price_id_is_one_time(): void
    {
        $plan = Plan::create([
            'name' => 'Basic', 'slug' => 'basic-'.uniqid(),
            'price_monthly' => 5, 'price_yearly' => 50, 'vat_percentage' => 0, 'credits' => 500,
            'is_active' => true, 'is_free' => false, 'sort_order' => 1,
        ]);

        $this->assertFalse($this->willBeRecurring($this->stripeGateway(), $plan, 'monthly', null, 'default', 0.0));
    }

    // ─── the two decisions must agree ────────────

    public function test_stored_payment_eligibility_matches_the_checkout_decision(): void
    {
        $plan = $this->recurringPlan();

        $recurring = new Payment(['plan_id' => $plan->id, 'metadata' => [
            'billing_cycle' => 'monthly', 'coupon_code' => null, 'pricing_source' => 'default', 'proration_credit' => 0,
        ]]);
        $recurring->setRelation('plan', $plan);
        $this->assertTrue($this->isRecurringEligible($recurring));

        $withCoupon = new Payment(['plan_id' => $plan->id, 'metadata' => [
            'billing_cycle' => 'monthly', 'coupon_code' => 'SAVE10', 'pricing_source' => 'default', 'proration_credit' => 0,
        ]]);
        $withCoupon->setRelation('plan', $plan);
        $this->assertFalse($this->isRecurringEligible($withCoupon));

        $lifetime = new Payment(['plan_id' => $plan->id, 'metadata' => [
            'billing_cycle' => 'lifetime', 'pricing_source' => 'default', 'proration_credit' => 0,
        ]]);
        $lifetime->setRelation('plan', $plan);
        $this->assertFalse($this->isRecurringEligible($lifetime));
    }
}
