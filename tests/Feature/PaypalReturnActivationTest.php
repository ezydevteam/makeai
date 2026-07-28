<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\Payment\PaymentActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A PayPal return has to activate the thing that was actually bought.
 *
 * CreditTopupController points its PayPal return_url at checkout.paypal.return — the same
 * handler subscriptions use — but that handler called activateFromPayment() for every
 * capture. For a top-up that meant opening a billing_subscriptions row with the payment's
 * plan_id, which is null on a top-up: "SQLSTATE[23000] Column 'plan_id' cannot be null",
 * a 500 shown to the buyer AFTER PayPal had taken the money. Seen in production on
 * 2026-07-28 against a $5 top-up.
 *
 * ProcessPaymentWebhookJob::activatePayment() had always branched on payment type; the
 * browser-return path never did, so it only broke for buyers who got back before the
 * webhook landed.
 *
 * Asserted against the activation service rather than through an HTTP round trip, since
 * reaching the controller means capturing a real PayPal order.
 */
class PaypalReturnActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instance(
            \App\Services\NotificationEventService::class,
            \Mockery::mock(\App\Services\NotificationEventService::class)->shouldIgnoreMissing(),
        );
    }

    private function topupPayment(User $user): Payment
    {
        return Payment::create([
            'user_id' => $user->id,
            'plan_id' => null,          // the crux: a top-up has no plan
            'gateway' => 'paypal',
            'gateway_payment_id' => 'paypal-order-'.uniqid(),
            'amount' => 5.00,
            'currency' => 'USD',
            'status' => 'pending',
            'type' => 'credit_topup',
            'metadata' => ['total_credits' => 500, 'base_credits' => 500, 'bonus_credits' => 0],
        ]);
    }

    public function test_a_topup_grants_credits_and_creates_no_subscription(): void
    {
        $user = User::factory()->create(['is_active' => true, 'credits' => 100]);
        $payment = $this->topupPayment($user);

        app(PaymentActivationService::class)->activateCreditTopup($payment, 'capture_1');

        $this->assertSame(600.0, (float) $user->fresh()->credits);
        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertDatabaseCount('billing_subscriptions', 0);
    }

    /** Top-ups are purchased credits, so they must also be protected at renewal. */
    public function test_a_topup_is_recorded_as_protected_credits(): void
    {
        $user = User::factory()->create(['is_active' => true, 'credits' => 100]);
        $payment = $this->topupPayment($user);

        app(PaymentActivationService::class)->activateCreditTopup($payment, 'capture_2');

        $this->assertSame(500.0, (float) $user->fresh()->topup_credits);
    }

    /**
     * The route the bug took: a top-up sent down the subscription path blows up on the
     * NOT NULL constraint. Pinned so nobody "simplifies" the branch back out.
     */
    public function test_activating_a_topup_as_a_subscription_still_fails_loudly(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $payment = $this->topupPayment($user);

        $this->expectException(\Illuminate\Database\QueryException::class);

        app(PaymentActivationService::class)->activateFromPayment($payment, 'capture_3', 'sub_3');
    }

    /** A real subscription payment still activates as one. */
    public function test_a_plan_payment_still_activates_a_subscription(): void
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 5, 'price_yearly' => 50, 'vat_percentage' => 0,
            'credits' => 1000, 'is_active' => true, 'is_free' => false, 'sort_order' => 1,
        ]);

        $user = User::factory()->create(['is_active' => true, 'credits' => 0]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'gateway' => 'paypal',
            'gateway_payment_id' => 'paypal-order-'.uniqid(),
            'amount' => 5.00,
            'currency' => 'USD',
            'status' => 'pending',
            'type' => 'subscription',
            'metadata' => ['billing_cycle' => 'monthly'],
        ]);

        app(PaymentActivationService::class)->activateFromPayment($payment, 'capture_4', 'sub_4');

        $this->assertDatabaseCount('billing_subscriptions', 1);
        $this->assertSame(1000.0, (float) $user->fresh()->credits);
    }
}
