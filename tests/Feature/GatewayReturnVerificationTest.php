<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPremium;
use App\Http\Middleware\LicenseMiddleware;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Confirm-on-return for SSLCommerz, Paystack and CoinGate.
 *
 * All three pointed their success URL straight at /checkout/pending/{ulid}, a screen that
 * only renders the row's current status — so activation was webhook-only and a webhook
 * that never fired left a paying customer reading "waiting for confirmation" indefinitely.
 * That is the same defect Razorpay and Paddle each had, three more times.
 *
 * 2Checkout is deliberately absent: its buy-link flow makes no API call at checkout, so
 * there is no credential stored that could query the sale back. It stays webhook-only.
 */
class GatewayReturnVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        settings_set('license_type', '2', 'integer', 'license');
        config(['broadcasting.default' => 'null']);
        config(['inertia.testing.ensure_pages_exist' => false]);
        $this->withoutMiddleware([CheckPremium::class, LicenseMiddleware::class]);
    }

    private function gateway(string $slug, array $credentials): PaymentGateway
    {
        return PaymentGateway::create([
            'slug' => $slug, 'name' => ucfirst($slug), 'is_enabled' => true, 'is_test_mode' => true,
            'credentials' => PaymentGateway::encryptCredentials($credentials),
        ]);
    }

    private function payment(string $slug, float $amount = 50, string $gatewayPaymentId = 'gw_1'): Payment
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
            'user_id' => $user->id, 'plan_id' => $plan->id, 'gateway' => $slug,
            'amount' => $amount, 'currency' => 'USD', 'status' => 'pending', 'type' => 'subscription',
            'gateway_payment_id' => $gatewayPaymentId,
            'metadata' => ['billing_cycle' => 'monthly'],
        ]);
    }

    private function assertActivated(Payment $payment): void
    {
        $payment->refresh();
        $this->assertSame('completed', $payment->status);
        $this->assertSame((int) $payment->plan_id, (int) $payment->user->fresh()->plan_id);
    }

    // ─── Paystack ───────────────────────────────

    private function fakePaystack(array $data = []): void
    {
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => array_merge([
                'id' => 99, 'status' => 'success', 'amount' => 5000, 'currency' => 'USD',
            ], $data),
        ])]);
    }

    public function test_paystack_return_activates_without_a_webhook(): void
    {
        $this->gateway('paystack', ['secret_key' => 'sk_test', 'public_key' => 'pk_test']);
        $payment = $this->payment('paystack');
        $this->fakePaystack();

        $this->get(route('checkout.paystack.return', $payment))
            ->assertRedirect(route('checkout.pending', $payment));

        $this->assertActivated($payment);
    }

    /** Paystack reports minor units; 1000 kobo against a $50 order must not pass. */
    public function test_paystack_return_rejects_an_underpayment(): void
    {
        $this->gateway('paystack', ['secret_key' => 'sk_test']);
        $payment = $this->payment('paystack');
        $this->fakePaystack(['amount' => 1000]);

        $this->get(route('checkout.paystack.return', $payment));

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_paystack_return_ignores_an_abandoned_transaction(): void
    {
        $this->gateway('paystack', ['secret_key' => 'sk_test']);
        $payment = $this->payment('paystack');
        $this->fakePaystack(['status' => 'abandoned']);

        $this->get(route('checkout.paystack.return', $payment));

        $this->assertSame('pending', $payment->fresh()->status);
    }

    // ─── CoinGate ───────────────────────────────

    private function fakeCoinGate(array $body = []): void
    {
        Http::fake(['*coingate.com/v2/orders/*' => Http::response(array_merge([
            'id' => 4242, 'status' => 'paid', 'price_amount' => '50.00', 'price_currency' => 'USD',
        ], $body))]);
    }

    public function test_coingate_return_activates_without_a_webhook(): void
    {
        $this->gateway('coingate', ['auth_token' => 'tok']);
        $payment = $this->payment('coingate', gatewayPaymentId: '4242');
        $this->fakeCoinGate();

        $this->get(route('checkout.coingate.return', $payment))
            ->assertRedirect(route('checkout.pending', $payment));

        $this->assertActivated($payment);
    }

    /** Crypto orders can settle short; a partial payment must not buy a plan. */
    public function test_coingate_return_rejects_an_underpaid_order(): void
    {
        $this->gateway('coingate', ['auth_token' => 'tok']);
        $payment = $this->payment('coingate', gatewayPaymentId: '4242');
        $this->fakeCoinGate(['price_amount' => '12.00']);

        $this->get(route('checkout.coingate.return', $payment));

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_coingate_return_ignores_a_pending_order(): void
    {
        $this->gateway('coingate', ['auth_token' => 'tok']);
        $payment = $this->payment('coingate', gatewayPaymentId: '4242');
        $this->fakeCoinGate(['status' => 'pending']);

        $this->get(route('checkout.coingate.return', $payment));

        $this->assertSame('pending', $payment->fresh()->status);
    }

    // ─── SSLCommerz ─────────────────────────────

    private function fakeSslCommerz(Payment $payment, array $element = []): void
    {
        Http::fake(['*sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php*' => Http::response([
            'APIConnect' => 'DONE',
            'element' => [array_merge([
                'status' => 'VALID', 'tran_id' => $payment->ulid, 'bank_tran_id' => 'bank_1',
                'currency_amount' => '50.00', 'currency_type' => 'USD',
            ], $element)],
        ])]);
    }

    /**
     * SSLCommerz returns the buyer by POSTing a form from its own domain. The success URL
     * used to be the GET-only pending route, so every SSLCommerz payment came back to a
     * 405 — and with CSRF applied it would have been a 419 even as a POST route.
     */
    public function test_sslcommerz_returns_the_buyer_by_post_and_activates(): void
    {
        $this->gateway('sslcommerz', ['store_id' => 'store', 'store_password' => 'pass']);
        $payment = $this->payment('sslcommerz');
        $this->fakeSslCommerz($payment);

        $this->post(route('checkout.sslcommerz.return', $payment), [
            'status' => 'VALID', 'tran_id' => $payment->ulid, 'val_id' => 'val_1',
        ])->assertRedirect(route('checkout.pending', $payment));

        $this->assertActivated($payment);
    }

    /** The POSTed body is never trusted — only the API answer decides. */
    public function test_sslcommerz_ignores_a_forged_success_post(): void
    {
        $this->gateway('sslcommerz', ['store_id' => 'store', 'store_password' => 'pass']);
        $payment = $this->payment('sslcommerz');
        // The API says the transaction was never validated.
        $this->fakeSslCommerz($payment, ['status' => 'FAILED']);

        $this->post(route('checkout.sslcommerz.return', $payment), [
            'status' => 'VALID', 'tran_id' => $payment->ulid, 'val_id' => 'forged',
        ])->assertRedirect(route('checkout.pending', $payment));

        $this->assertSame('pending', $payment->fresh()->status);
    }

    /** A row for a different transaction must not settle this payment. */
    public function test_sslcommerz_ignores_an_element_for_another_transaction(): void
    {
        $this->gateway('sslcommerz', ['store_id' => 'store', 'store_password' => 'pass']);
        $payment = $this->payment('sslcommerz');
        $this->fakeSslCommerz($payment, ['tran_id' => 'someone-elses-ulid']);

        $this->post(route('checkout.sslcommerz.return', $payment));

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_sslcommerz_return_rejects_a_currency_mismatch(): void
    {
        $this->gateway('sslcommerz', ['store_id' => 'store', 'store_password' => 'pass']);
        $payment = $this->payment('sslcommerz');
        $this->fakeSslCommerz($payment, ['currency_type' => 'BDT']);

        $this->post(route('checkout.sslcommerz.return', $payment));

        $this->assertSame('pending', $payment->fresh()->status);
    }

    // ─── Checkout wiring ────────────────────────

    private function checkout(string $slug, float $amount = 50): \Illuminate\Testing\TestResponse
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => $amount, 'price_yearly' => $amount * 10,
            'vat_percentage' => 0, 'credits' => 1000,
            'is_active' => true, 'is_free' => false, 'sort_order' => 2,
        ]);

        $this->actingAs(User::factory()->create(['is_active' => true, 'email_verified_at' => now()]));

        return $this->post(route('checkout.session'), [
            'plan' => $plan->slug, 'billing' => 'monthly', 'gateway' => $slug,
        ]);
    }

    /**
     * The verification above is worthless if the gateway is still told to send the buyer
     * to the bare pending screen, so assert the wiring, not just the handler.
     */
    public function test_each_gateway_is_told_to_return_through_its_verifying_route(): void
    {
        $this->gateway('paystack', ['secret_key' => 'sk_test']);
        Http::fake(['*' => Http::response(['status' => true, 'data' => [
            'reference' => 'r', 'access_code' => 'a', 'authorization_url' => 'https://paystack.test/pay',
        ]])]);

        $this->checkout('paystack');

        Http::assertSent(fn ($request) => str_contains((string) ($request->data()['callback_url'] ?? ''), '/checkout/paystack/return/'));
    }

    public function test_sslcommerz_is_told_to_return_through_its_post_capable_route(): void
    {
        $this->gateway('sslcommerz', ['store_id' => 'store', 'store_password' => 'pass']);
        Http::fake(['*' => Http::response(['GatewayPageURL' => 'https://ssl.test/pay', 'sessionkey' => 'sk'])]);

        $this->checkout('sslcommerz');

        Http::assertSent(function ($request) {
            $data = $request->data();

            // Both success and fail land on the verifying route — a failed payment that
            // 405s is just as broken as a successful one that does.
            return str_contains((string) ($data['success_url'] ?? ''), '/checkout/sslcommerz/return/')
                && str_contains((string) ($data['fail_url'] ?? ''), '/checkout/sslcommerz/return/');
        });
    }

    public function test_coingate_is_told_to_return_through_its_verifying_route(): void
    {
        $this->gateway('coingate', ['auth_token' => 'tok']);
        Http::fake(['*' => Http::response(['id' => 4242, 'payment_url' => 'https://coingate.test/pay'])]);

        $this->checkout('coingate');

        Http::assertSent(fn ($request) => str_contains((string) ($request->data()['success_url'] ?? ''), '/checkout/coingate/return/'));
    }

    /** A timeout while creating the session must fail the checkout, not 500 it. */
    public function test_a_checkout_timeout_fails_the_session_readably(): void
    {
        $this->gateway('coingate', ['auth_token' => 'tok']);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28'));

        $this->checkout('coingate')
            ->assertSessionHas('error', fn ($error) => str_contains($error, 'Could not reach'));

        $this->assertDatabaseHas('payments', ['gateway' => 'coingate', 'status' => 'failed']);
    }

    // ─── Shared behaviour ───────────────────────

    /**
     * A lookup that never gets a response must not 500 a buyer who has already paid —
     * the production failure mode that took down Paddle's return page (cURL error 28).
     */
    public function test_a_connection_timeout_lands_on_the_pending_page(): void
    {
        $this->gateway('paystack', ['secret_key' => 'sk_test']);
        $payment = $this->payment('paystack');
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28'));

        $this->get(route('checkout.paystack.return', $payment))
            ->assertRedirect(route('checkout.pending', $payment));

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_another_users_payment_is_not_reachable(): void
    {
        $this->gateway('paystack', ['secret_key' => 'sk_test']);
        $payment = $this->payment('paystack');
        $this->actingAs(User::factory()->create(['is_active' => true, 'email_verified_at' => now()]));

        $this->get(route('checkout.paystack.return', $payment))->assertNotFound();
    }

    /** 2Checkout has no verifiable lookup, so it must not be routed through this handler. */
    public function test_an_unverifiable_gateway_is_rejected_by_the_return_handler(): void
    {
        $this->gateway('2checkout', ['merchant_code' => 'm', 'secret_key' => 's']);
        $payment = $this->payment('2checkout');

        $this->get(route('checkout.paystack.return', $payment))->assertNotFound();
    }

    /** The pending screen offers polling for each verifiable gateway, not just Paddle. */
    public function test_the_pending_screen_offers_polling_for_each_verifiable_gateway(): void
    {
        foreach ([
            'paystack' => ['secret_key' => 'sk_test'],
            'coingate' => ['auth_token' => 'tok'],
            'sslcommerz' => ['store_id' => 'store', 'store_password' => 'pass'],
        ] as $slug => $credentials) {
            $this->gateway($slug, $credentials);
            $payment = $this->payment($slug);

            $this->get(route('checkout.pending', $payment))
                ->assertInertia(fn ($page) => $page->where('statusUrl', route('checkout.status', $payment)));
        }
    }

    /** 2Checkout cannot be polled, so the screen must not advertise it. */
    public function test_the_pending_screen_does_not_offer_polling_for_2checkout(): void
    {
        $this->gateway('2checkout', ['merchant_code' => 'm']);
        $payment = $this->payment('2checkout');

        $this->get(route('checkout.pending', $payment))
            ->assertInertia(fn ($page) => $page->where('statusUrl', null));
    }
}
