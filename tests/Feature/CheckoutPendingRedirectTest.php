<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The end of the payment flow: one screen, every gateway, honest about what happened.
 *
 * Most gateways point their success_url straight at /checkout/pending/{ulid} and let a
 * webhook finish the payment, so whenever that webhook won the race the buyer arrived at
 * a page headed "Payment is waiting for confirmation" with a status of "completed"
 * underneath — the page contradicting itself about whether their money went through.
 * Reported from production against PayPal, but it was never PayPal-specific: only PayPal
 * has a PHP return handler, and every other gateway hits this URL directly.
 */
class CheckoutPendingRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The whole /checkout/* group sits behind the `extended` middleware, which 404s
        // on a non-Extended licence — so without this every assertion below tests the
        // licence gate rather than the redirect.
        settings_set('license_type', '2', 'integer', 'license');

        // Theme pages live in resources/themes/default/js and are resolved by a custom
        // branch in app.ts; Inertia's on-disk existence check only knows the default
        // location, so it fails a component that resolves correctly in the browser.
        config(['inertia.testing.ensure_pages_exist' => false]);
    }

    private function payment(string $status): Payment
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'gateway' => 'paypal',
            'gateway_payment_id' => 'demo-'.$status.'-'.uniqid(),
            'amount' => 49.99,
            'currency' => 'USD',
            'status' => $status,
            'type' => 'subscription',
        ]);

        $this->actingAs($user);

        return $payment;
    }

    /**
     * A completed payment now RENDERS the confirmation rather than redirecting past it.
     *
     * The redirect existed only because the page used to insist it was "waiting for
     * confirmation" whatever the status. Now that it is status-aware, a buyer who has
     * just parted with money gets a moment that says so — and the page forwards them on
     * itself, on a visible countdown they can cancel.
     */
    public function test_a_completed_payment_renders_the_confirmation_with_a_forward_target(): void
    {
        $payment = $this->payment('completed');

        $this->get(route('checkout.pending', $payment))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Checkout/Pending')
                ->where('payment.status', 'completed')
                ->where('continueUrl', route('user.dashboard.billing'))
            );
    }

    /** A top-up buyer wants the credits they bought, not the billing page. */
    public function test_a_completed_topup_forwards_to_usage_instead_of_billing(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'gateway' => 'stripe',
            'gateway_payment_id' => 'demo-topup-'.uniqid(),
            'amount' => 5,
            'currency' => 'USD',
            'status' => 'completed',
            'type' => 'credit_topup',
        ]);

        $this->actingAs($user)
            ->get(route('checkout.pending', $payment))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('continueUrl', route('user.dashboard.usage.index'))
            );
    }

    /** The screen still exists for payments that genuinely have not settled. */
    public function test_a_pending_payment_still_renders_the_pending_screen(): void
    {
        $payment = $this->payment('pending');

        $this->get(route('checkout.pending', $payment))->assertOk();
    }

    /**
     * A failed payment is not a success and must not be swept to billing with a green
     * flash — the buyer needs to see that it did not go through.
     */
    public function test_a_failed_payment_still_renders_the_pending_screen(): void
    {
        $payment = $this->payment('failed');

        $this->get(route('checkout.pending', $payment))->assertOk();
    }

    /** The ownership check has to survive the new redirect branch. */
    public function test_another_users_completed_payment_is_still_a_404(): void
    {
        $payment = $this->payment('completed');

        $this->actingAs(User::factory()->create(['is_active' => true, 'email_verified_at' => now()]));

        $this->get(route('checkout.pending', $payment))->assertNotFound();
    }
}
