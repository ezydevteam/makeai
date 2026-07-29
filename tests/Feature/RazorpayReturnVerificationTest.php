<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Razorpay activation must not depend on the webhook alone.
 *
 * Razorpay's payment link used to send the buyer back to /checkout/pending/{ulid}, a page
 * that only renders whatever status the row already has. That made `payment_link.paid` the
 * single path to activation, so a webhook that was never registered in the dashboard, never
 * delivered, or rejected for a signature mismatch left a buyer who had genuinely paid on a
 * page reading "waiting for confirmation" indefinitely — the exact production symptom.
 *
 * These cover the return handler that now confirms the charge server-side, and in
 * particular that it decides from the Razorpay API rather than from the query string the
 * buyer's browser carries back.
 */
class RazorpayReturnVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // /checkout/* sits behind the `extended` middleware, which 404s on a lesser
        // licence — without this every assertion below tests the licence gate instead.
        settings_set('license_type', '2', 'integer', 'license');
        config(['broadcasting.default' => 'null']);

        PaymentGateway::create([
            'slug' => 'razorpay', 'name' => 'Razorpay', 'is_enabled' => true,
            'credentials' => PaymentGateway::encryptCredentials([
                'key_id' => 'rzp_test_key', 'key_secret' => 'secret',
            ]),
        ]);
    }

    private function pendingPayment(float $amount = 50, string $type = 'subscription'): Payment
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => $amount, 'price_yearly' => $amount * 10,
            'vat_percentage' => 0, 'credits' => 1000,
            'is_active' => true, 'is_free' => false, 'sort_order' => 2,
        ]);

        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $this->actingAs($user);

        return Payment::create([
            'user_id' => $user->id,
            'plan_id' => $type === 'credit_topup' ? null : $plan->id,
            'gateway' => 'razorpay',
            'gateway_payment_id' => 'plink_ABC',
            'amount' => $amount,
            'currency' => 'USD',
            'status' => 'pending',
            'type' => $type,
            'metadata' => ['billing_cycle' => 'monthly'],
        ]);
    }

    private function fakeLink(array $overrides = []): void
    {
        Http::fake(['api.razorpay.com/v1/payment_links/*' => Http::response(array_merge([
            'id' => 'plink_ABC',
            'status' => 'paid',
            'amount' => 5000,
            'amount_paid' => 5000,
            'currency' => 'USD',
            'payments' => [['payment_id' => 'pay_REAL', 'status' => 'captured']],
        ], $overrides))]);
    }

    /** The bug this whole handler exists for: paid at Razorpay, no webhook, still activated. */
    public function test_a_paid_payment_link_activates_the_subscription_without_any_webhook(): void
    {
        $payment = $this->pendingPayment();
        $this->fakeLink();

        $this->get(route('checkout.razorpay.return', $payment))
            ->assertRedirect(route('checkout.pending', $payment));

        $payment->refresh();
        $this->assertSame('completed', $payment->status);
        // The pay_… id off the link, not the plink_… container it arrived in.
        $this->assertSame('pay_REAL', $payment->gateway_payment_id);
        $this->assertSame((int) $payment->plan_id, (int) $payment->user->fresh()->plan_id);
        $this->assertDatabaseHas('billing_subscriptions', [
            'user_id' => $payment->user_id,
            'plan_id' => $payment->plan_id,
            'status' => 'active',
        ]);
    }

    /**
     * The security case. Razorpay appends razorpay_payment_id to the callback, but that
     * reaches us through the buyer's browser and a redirect can be typed by hand. Only the
     * server-to-server read decides, so a forged return on an unpaid link grants nothing.
     */
    public function test_an_unpaid_link_is_not_activated_by_a_hand_crafted_return_url(): void
    {
        $payment = $this->pendingPayment();
        $this->fakeLink(['status' => 'created', 'amount_paid' => 0, 'payments' => []]);

        $this->get(route('checkout.razorpay.return', $payment).'?razorpay_payment_id=pay_FAKE&razorpay_payment_link_status=paid')
            ->assertRedirect(route('checkout.pending', $payment));

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertNull($payment->user->fresh()->plan_id);
    }

    /**
     * A lookup that never gets a response. ConnectionException is not covered by
     * `$response->failed()`, and an uncaught one 500s a buyer who has already paid.
     */
    public function test_a_connection_timeout_lands_on_the_pending_page_not_an_error(): void
    {
        $payment = $this->pendingPayment();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out'));

        $this->get(route('checkout.razorpay.return', $payment))
            ->assertRedirect(route('checkout.pending', $payment));

        $this->assertSame('pending', $payment->fresh()->status);
    }

    /** Razorpay links accept partial payment; a short payment must not buy a plan. */
    public function test_an_underpaid_link_is_rejected(): void
    {
        $payment = $this->pendingPayment();
        $this->fakeLink(['amount_paid' => 1000]);

        $this->get(route('checkout.razorpay.return', $payment));

        $this->assertSame('pending', $payment->fresh()->status);
    }

    /** A link settled in a different currency than the order was priced in. */
    public function test_a_currency_mismatch_is_rejected(): void
    {
        $payment = $this->pendingPayment();
        $this->fakeLink(['currency' => 'INR']);

        $this->get(route('checkout.razorpay.return', $payment));

        $this->assertSame('pending', $payment->fresh()->status);
    }

    /**
     * Top-ups come back through this same handler and carry no plan_id — routing one into
     * activateFromPayment() would blow up on a null plan_id after the money was taken.
     */
    public function test_a_credit_topup_credits_the_wallet_instead_of_granting_a_plan(): void
    {
        $payment = $this->pendingPayment(50, 'credit_topup');
        $payment->update(['metadata' => ['total_credits' => 500, 'base_credits' => 500, 'bonus_credits' => 0]]);
        $before = (int) $payment->user->credits;
        $this->fakeLink();

        $this->get(route('checkout.razorpay.return', $payment));

        $payment->refresh();
        $this->assertSame('completed', $payment->status);
        $this->assertSame($before + 500, (int) $payment->user->fresh()->credits);
    }

    /** Whichever of webhook/return lands second must be a no-op, not a second activation. */
    public function test_an_already_completed_payment_is_not_reactivated(): void
    {
        $payment = $this->pendingPayment();
        $payment->update(['status' => 'completed', 'gateway_payment_id' => 'pay_FIRST']);
        Http::fake();

        $this->get(route('checkout.razorpay.return', $payment))
            ->assertRedirect(route('checkout.pending', $payment));

        $this->assertSame('pay_FIRST', $payment->fresh()->gateway_payment_id);
        Http::assertNothingSent();
    }

    /** Someone else's payment is not yours to confirm. */
    public function test_another_users_payment_is_not_reachable(): void
    {
        $payment = $this->pendingPayment();
        $this->actingAs(User::factory()->create(['is_active' => true, 'email_verified_at' => now()]));

        $this->get(route('checkout.razorpay.return', $payment))->assertNotFound();
    }
}
