<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPremium;
use App\Http\Middleware\LicenseMiddleware;
use App\Jobs\ProcessPaymentWebhookJob;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use App\Services\Payment\PaymentActivationService;
use App\Services\Subscription\SubscriptionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Paddle Billing.
 *
 * This integration was originally written for Paddle Classic — `vendors.paddle.com/api/
 * 2.0/product/generate_pay_link`, RSA-signed `alert_name` webhooks — which has not
 * accepted new signups since Billing launched in Aug 2023. A Billing key cannot
 * authenticate against Classic at all, so checkout came back with Paddle's own "You don't
 * have permission to access this resource" in a toast. Classic has since been removed
 * outright; this is the only Paddle path, and these are the only Paddle webhook tests.
 */
class PaddleBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        settings_set('license_type', '2', 'integer', 'license');
        config(['broadcasting.default' => 'null']);

        // The checkout route sits behind the premium + license gates; neither is what
        // these tests are about, and without this every assertion checks the licence
        // instead of the Paddle call.
        $this->withoutMiddleware([CheckPremium::class, LicenseMiddleware::class]);
    }

    private function gateway(bool $testMode = true): PaymentGateway
    {
        return PaymentGateway::create([
            'slug' => 'paddle', 'name' => 'Paddle', 'is_enabled' => true, 'is_test_mode' => $testMode,
            // Paddle's real key shapes — no `pdl_` prefix. An earlier build discriminated
            // Billing from Classic on that prefix and so sent every real key down the
            // Classic path; Classic is gone now, but the formats stay documented here.
            'credentials' => PaymentGateway::encryptCredentials([
                'api_key' => 'apikey_01kyprxvcm3mayfgym28vthzdm',
                'client_token' => 'ctkn_01test',
                'webhook_secret' => 'ntfset_01kyps2xg0z01ajdzded52bps9',
            ]),
        ]);
    }

    private function pendingPayment(float $amount = 50): Payment
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => $amount, 'price_yearly' => $amount * 10,
            'vat_percentage' => 0, 'credits' => 1000,
            'is_active' => true, 'is_free' => false, 'sort_order' => 2,
        ]);

        return Payment::create([
            'user_id' => User::factory()->create(['is_active' => true, 'email_verified_at' => now()])->id,
            'plan_id' => $plan->id,
            'gateway' => 'paddle',
            'amount' => $amount,
            'currency' => 'USD',
            'status' => 'pending',
            'type' => 'subscription',
            'metadata' => ['billing_cycle' => 'monthly'],
        ]);
    }

    private function handleWebhook(array $payload, string $secret = 'ntfset_01kyps2xg0z01ajdzded52bps9', ?string $rawOverride = null): void
    {
        $raw = $rawOverride ?? json_encode($payload);
        $ts = '1700000000';
        $sig = hash_hmac('sha256', $ts.':'.$raw, $secret);

        (new ProcessPaymentWebhookJob('paddle', $payload, $raw, ['paddle-signature' => "ts={$ts};h1={$sig}"]))->handle(
            app(SubscriptionLifecycleService::class),
            app(PaymentActivationService::class),
        );
    }

    private function superAdmin(): \App\Models\Admin
    {
        $role = \App\Models\AdminRole::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);

        return \App\Models\Admin::create([
            'name' => 'Root', 'email' => 'root@example.com', 'password' => 'password',
            'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    private function completedEvent(Payment $payment, string $total = '5000', string $currency = 'USD'): array
    {
        return [
            'event_id' => 'evt_01',
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_01ABC',
                'status' => 'completed',
                'currency_code' => $currency,
                'custom_data' => ['payment_ulid' => $payment->ulid],
                'details' => ['totals' => ['total' => $total]],
            ],
        ];
    }

    // ─── Checkout ───────────────────────────────

    private function checkout(Plan $plan): \Illuminate\Testing\TestResponse
    {
        $this->actingAs(User::factory()->create(['is_active' => true, 'email_verified_at' => now()]));

        return $this->post(route('checkout.session'), [
            'plan' => $plan->slug, 'billing' => 'monthly', 'gateway' => 'paddle',
        ]);
    }

    private function plan(float $amount = 50): Plan
    {
        return Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => $amount, 'price_yearly' => $amount * 10,
            'vat_percentage' => 0, 'credits' => 1000,
            'is_active' => true, 'is_free' => false, 'sort_order' => 2,
        ]);
    }

    /**
     * The bug itself: a Billing key must go to sandbox-api.paddle.com/transactions, never
     * to Classic's vendors.paddle.com — which is what returned the permission error.
     */
    public function test_a_billing_key_creates_a_transaction_against_the_sandbox_api(): void
    {
        $this->gateway();
        $plan = $this->plan();

        Http::fake(['*' => Http::response([
            'data' => ['id' => 'txn_01ABC', 'checkout' => ['url' => 'https://pay.example.com/?_ptxn=txn_01ABC']],
        ])]);

        $this->checkout($plan)->assertRedirect('https://pay.example.com/?_ptxn=txn_01ABC');

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://sandbox-api.paddle.com/transactions'
                && $request->hasHeader('Authorization', 'Bearer apikey_01kyprxvcm3mayfgym28vthzdm')
                // Minor units, as a string — Paddle rejects an integer.
                && $body['items'][0]['price']['unit_price'] === ['amount' => '5000', 'currency_code' => 'USD']
                // The ulid has to survive all the way to the webhook.
                && ! empty($body['custom_data']['payment_ulid']);
        });

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'vendors.paddle.com'));
    }

    /** Live mode must not talk to the sandbox host. */
    public function test_live_mode_uses_the_production_api_host(): void
    {
        $this->gateway(testMode: false);
        $plan = $this->plan();

        Http::fake(['*' => Http::response([
            'data' => ['id' => 'txn_01ABC', 'checkout' => ['url' => 'https://pay.example.com/?_ptxn=txn_01ABC']],
        ])]);

        $this->checkout($plan);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.paddle.com/transactions');
    }

    /**
     * Paddle only fills checkout.url from the seller's default payment link. Redirecting
     * to an empty string would strand the buyer with no explanation and a pending row.
     */
    public function test_a_missing_checkout_url_fails_the_session_with_an_actionable_message(): void
    {
        $this->gateway();
        $plan = $this->plan();

        Http::fake(['*' => Http::response(['data' => ['id' => 'txn_01ABC', 'checkout' => null]])]);

        $this->checkout($plan)->assertSessionHas('error', fn ($error) => str_contains($error, 'default payment link'));

        $this->assertDatabaseHas('payments', ['gateway' => 'paddle', 'status' => 'failed']);
    }

    /** Paddle Billing nests its error text under error.detail, not error.message. */
    public function test_a_paddle_api_error_surfaces_its_detail(): void
    {
        $this->gateway();
        $plan = $this->plan();

        Http::fake(['*' => Http::response([
            'error' => ['type' => 'request_error', 'code' => 'forbidden', 'detail' => 'Nope, not allowed.'],
        ], 403)]);

        $this->checkout($plan)->assertSessionHas('error', 'Nope, not allowed.');
    }

    // ─── Status polling fallback ────────────────

    private function fakeTransaction(array $overrides = []): void
    {
        Http::fake(['*/transactions/*' => Http::response(['data' => array_merge([
            'id' => 'txn_01ABC',
            'status' => 'completed',
            'currency_code' => 'USD',
            'details' => ['totals' => ['total' => '5000']],
        ], $overrides)])]);
    }

    private function pendingPaddlePayment(float $amount = 50): Payment
    {
        $payment = $this->pendingPayment($amount);
        $payment->update(['gateway_payment_id' => 'txn_01ABC']);
        $this->actingAs($payment->user);

        return $payment;
    }

    /** The whole point: no webhook ever arrives, and the buyer still gets their plan. */
    public function test_polling_activates_a_payment_the_webhook_never_confirmed(): void
    {
        $this->gateway();
        $payment = $this->pendingPaddlePayment();
        $this->fakeTransaction();

        $this->getJson(route('checkout.status', $payment))
            ->assertOk()
            ->assertJson(['status' => 'completed', 'settled' => true]);

        $this->assertSame((int) $payment->plan_id, (int) $payment->user->fresh()->plan_id);
    }

    /** `paid` is collected but still settling internally — good enough to hand over. */
    public function test_polling_accepts_a_paid_but_not_yet_completed_transaction(): void
    {
        $this->gateway();
        $payment = $this->pendingPaddlePayment();
        $this->fakeTransaction(['status' => 'paid']);

        $this->getJson(route('checkout.status', $payment))->assertJson(['settled' => true]);
    }

    /** Still at the payment screen — report pending, activate nothing. */
    public function test_polling_leaves_an_unpaid_transaction_pending(): void
    {
        $this->gateway();
        $payment = $this->pendingPaddlePayment();
        $this->fakeTransaction(['status' => 'ready']);

        $this->getJson(route('checkout.status', $payment))
            ->assertJson(['status' => 'pending', 'settled' => false]);

        $this->assertNull($payment->user->fresh()->plan_id);
    }

    /** The amount guard applies to this path too, not just the webhook. */
    public function test_polling_rejects_an_underpaid_transaction(): void
    {
        $this->gateway();
        $payment = $this->pendingPaddlePayment();
        $this->fakeTransaction(['details' => ['totals' => ['total' => '1000']]]);

        $this->getJson(route('checkout.status', $payment))->assertJson(['settled' => false]);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    /** A gateway outage must not settle anything, and must not 500 the poll. */
    public function test_polling_survives_a_paddle_api_failure(): void
    {
        $this->gateway();
        $payment = $this->pendingPaddlePayment();
        Http::fake(['*' => Http::response(['error' => ['detail' => 'boom']], 500)]);

        $this->getJson(route('checkout.status', $payment))
            ->assertOk()
            ->assertJson(['status' => 'pending', 'settled' => false]);
    }

    /**
     * A request that never gets a response at all.
     *
     * `$response->failed()` does not cover this — a timeout, DNS failure or refused
     * connection throws ConnectionException, and an uncaught one turned a slow Paddle
     * call into a 500 on the return page for a buyer who had already paid
     * (cURL error 28 against sandbox-api.paddle.com, seen in production).
     */
    public function test_polling_survives_a_connection_timeout(): void
    {
        $this->gateway();
        $payment = $this->pendingPaddlePayment();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out'));

        $this->getJson(route('checkout.status', $payment))
            ->assertOk()
            ->assertJson(['status' => 'pending', 'settled' => false]);
    }

    /** The same timeout on the return leg must land on the pending page, not a 500. */
    public function test_the_return_route_survives_a_connection_timeout(): void
    {
        $this->gateway();
        $payment = $this->pendingPaddlePayment();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out'));

        $this->get(route('checkout.paddle.return'))
            ->assertRedirect(route('checkout.pending', $payment));

        $this->assertSame('pending', $payment->fresh()->status);
    }

    /** And at checkout, where there is no webhook to fall back on. */
    public function test_checkout_survives_a_connection_timeout(): void
    {
        $this->gateway();
        $plan = $this->plan();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out'));

        $this->checkout($plan)
            ->assertSessionHas('error', fn ($error) => str_contains($error, 'Could not reach Paddle'));

        $this->assertDatabaseHas('payments', ['gateway' => 'paddle', 'status' => 'failed']);
    }

    /** A settled payment needs no gateway call at all. */
    public function test_polling_does_not_call_paddle_for_an_already_completed_payment(): void
    {
        $this->gateway();
        $payment = $this->pendingPaddlePayment();
        $payment->update(['status' => 'completed']);
        Http::fake();

        $this->getJson(route('checkout.status', $payment))->assertJson(['settled' => true]);

        Http::assertNothingSent();
    }

    public function test_polling_another_users_payment_is_not_allowed(): void
    {
        $this->gateway();
        $payment = $this->pendingPaddlePayment();
        $this->actingAs(User::factory()->create(['is_active' => true, 'email_verified_at' => now()]));

        $this->getJson(route('checkout.status', $payment))->assertNotFound();
    }

    /** The pending screen only advertises polling when there is something to poll. */
    public function test_the_pending_screen_exposes_a_status_url_only_while_pending(): void
    {
        $this->gateway();
        $payment = $this->pendingPaddlePayment();
        config(['inertia.testing.ensure_pages_exist' => false]);

        $this->get(route('checkout.pending', $payment))
            ->assertInertia(fn ($page) => $page->where('statusUrl', route('checkout.status', $payment)));

        $payment->update(['status' => 'completed']);

        $this->get(route('checkout.pending', $payment))
            ->assertInertia(fn ($page) => $page->where('statusUrl', null));
    }

    /** Nothing to poll before a transaction exists to poll for. */
    public function test_a_payment_with_no_transaction_id_gets_no_status_url(): void
    {
        $this->gateway();
        $payment = $this->pendingPaddlePayment();
        $payment->update(['gateway_payment_id' => null]);
        config(['inertia.testing.ensure_pages_exist' => false]);

        $this->get(route('checkout.pending', $payment))
            ->assertInertia(fn ($page) => $page->where('statusUrl', null));
    }

    // ─── The default payment link page ──────────

    /**
     * Paddle does not host its checkout for us: it appends ?_ptxn= to a page on our own
     * domain and expects Paddle.js there. This is that page.
     */
    public function test_the_payment_link_page_hands_paddle_js_what_it_needs(): void
    {
        $this->gateway();
        $payment = $this->pendingPaddlePayment();
        config(['inertia.testing.ensure_pages_exist' => false]);

        $this->get(route('checkout.paddle.pay', ['_ptxn' => 'txn_01ABC']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Checkout/Paddle')
                ->where('transactionId', 'txn_01ABC')
                // The CLIENT token, never the secret API key.
                ->where('clientToken', 'ctkn_01test')
                ->where('environment', 'sandbox')
                ->where('returnUrl', route('checkout.paddle.return'))
                ->where('fallbackUrl', route('checkout.pending', $payment))
            );
    }

    /** Live mode must not tell Paddle.js it is in sandbox. */
    public function test_the_payment_link_page_reports_the_live_environment(): void
    {
        $this->gateway(testMode: false);
        $this->pendingPaddlePayment();
        config(['inertia.testing.ensure_pages_exist' => false]);

        $this->get(route('checkout.paddle.pay', ['_ptxn' => 'txn_01ABC']))
            ->assertInertia(fn ($page) => $page->where('environment', 'production'));
    }

    /** The secret key must never reach the browser. */
    public function test_the_payment_link_page_never_exposes_the_api_key(): void
    {
        $this->gateway();
        $this->pendingPaddlePayment();
        config(['inertia.testing.ensure_pages_exist' => false]);

        $response = $this->get(route('checkout.paddle.pay', ['_ptxn' => 'txn_01ABC']));

        $response->assertDontSee('apikey_01kyprxvcm3mayfgym28vthzdm', false);
        $response->assertDontSee('ntfset_01kyps2xg0z01ajdzded52bps9', false);
    }

    /** Without a client token Paddle.js cannot initialize, so there is no page to show. */
    public function test_the_payment_link_page_404s_without_a_client_token(): void
    {
        PaymentGateway::create([
            'slug' => 'paddle', 'name' => 'Paddle', 'is_enabled' => true, 'is_test_mode' => true,
            'credentials' => PaymentGateway::encryptCredentials(['api_key' => 'apikey_01test']),
        ]);
        $this->actingAs(User::factory()->create(['is_active' => true, 'email_verified_at' => now()]));

        $this->get(route('checkout.paddle.pay', ['_ptxn' => 'txn_01ABC']))->assertNotFound();
    }

    /** Another user's transaction id must not resolve to their payment. */
    public function test_the_payment_link_page_does_not_leak_another_users_payment(): void
    {
        $this->gateway();
        $this->pendingPaddlePayment();
        $this->actingAs(User::factory()->create(['is_active' => true, 'email_verified_at' => now()]));
        config(['inertia.testing.ensure_pages_exist' => false]);

        $this->get(route('checkout.paddle.pay', ['_ptxn' => 'txn_01ABC']))
            ->assertInertia(fn ($page) => $page->where('fallbackUrl', route('user.dashboard.billing')));
    }

    /**
     * The admin has to paste this URL into Paddle by hand, so the settings screen has to
     * show it — an admin cannot be expected to know that /checkout/paddle/pay exists.
     */
    public function test_the_admin_gateway_screen_exposes_the_default_payment_link(): void
    {
        $this->gateway();
        config(['inertia.testing.ensure_pages_exist' => false]);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.payment-gateways.index'))
            ->assertOk()
            ->assertInertia(function ($page) {
                $paddle = collect($page->toArray()['props']['gateways'])->firstWhere('slug', 'paddle');

                $this->assertSame(url('/checkout/paddle/pay'), $paddle['payment_link_url']);
                $this->assertNotEmpty($paddle['payment_link_hint']);

                // Every other gateway hosts its own checkout and must not show the panel.
                return true;
            });
    }

    /** Gateways that host their own checkout get no payment-link panel. */
    public function test_other_gateways_have_no_payment_link(): void
    {
        $this->gateway();
        PaymentGateway::create(['slug' => 'razorpay', 'name' => 'Razorpay', 'is_enabled' => true]);
        config(['inertia.testing.ensure_pages_exist' => false]);

        $this->actingAs($this->superAdmin(), 'admin')
            ->get(route('admin.payment-gateways.index'))
            ->assertInertia(function ($page) {
                $razorpay = collect($page->toArray()['props']['gateways'])->firstWhere('slug', 'razorpay');

                $this->assertNull($razorpay['payment_link_url']);

                return true;
            });
    }

    // ─── Paddle's static success URL ────────────

    /**
     * Paddle's hosted checkout sends every buyer to one dashboard-configured URL, so the
     * landing route has to work out which payment it is about on its own.
     */
    public function test_the_return_route_resolves_the_buyers_pending_payment_and_confirms_it(): void
    {
        $this->gateway();
        $payment = $this->pendingPaddlePayment();
        $this->fakeTransaction();

        $this->get(route('checkout.paddle.return'))
            ->assertRedirect(route('checkout.pending', $payment));

        $this->assertSame('completed', $payment->fresh()->status);
    }

    /** When Paddle does append _ptxn, prefer it over "most recent". */
    public function test_the_return_route_prefers_an_explicit_transaction_id(): void
    {
        $this->gateway();
        $older = $this->pendingPaddlePayment();
        $older->update(['gateway_payment_id' => 'txn_OLDER']);

        $newer = $this->pendingPayment();
        $newer->update(['user_id' => $older->user_id, 'gateway_payment_id' => 'txn_01ABC']);
        $this->actingAs($older->user);
        $this->fakeTransaction();

        $this->get(route('checkout.paddle.return', ['_ptxn' => 'txn_OLDER']))
            ->assertRedirect(route('checkout.pending', $older));
    }

    /** Nothing in flight — send them somewhere sensible rather than 404ing. */
    public function test_the_return_route_falls_back_to_billing_when_nothing_is_pending(): void
    {
        $this->gateway();
        $this->actingAs(User::factory()->create(['is_active' => true, 'email_verified_at' => now()]));

        $this->get(route('checkout.paddle.return'))->assertRedirect(route('user.dashboard.billing'));
    }

    // ─── Webhooks ───────────────────────────────

    /** The happy path: a signed transaction.completed activates the pending payment. */
    public function test_transaction_completed_activates_the_subscription(): void
    {
        $this->gateway();
        $payment = $this->pendingPayment();

        $this->handleWebhook($this->completedEvent($payment));

        $payment->refresh();
        $this->assertSame('completed', $payment->status);
        $this->assertSame('txn_01ABC', $payment->gateway_payment_id);
        $this->assertSame((int) $payment->plan_id, (int) $payment->user->fresh()->plan_id);
        $this->assertDatabaseHas('billing_subscriptions', [
            'user_id' => $payment->user_id,
            'plan_id' => $payment->plan_id,
            'status' => 'active',
        ]);
    }

    /** A forged or misconfigured signature must not activate anything. */
    public function test_a_bad_signature_is_rejected(): void
    {
        $this->gateway();
        $payment = $this->pendingPayment();

        $this->handleWebhook($this->completedEvent($payment), 'the-wrong-secret');

        $this->assertSame('pending', $payment->fresh()->status);
    }

    /**
     * The signature covers the RAW body. If verification ever re-encodes the parsed array
     * instead, a byte-identical-but-differently-ordered body would still pass — so sign a
     * raw string whose re-encoding differs and assert it is accepted.
     */
    public function test_the_signature_is_computed_over_the_raw_body(): void
    {
        $this->gateway();
        $payment = $this->pendingPayment();
        $payload = $this->completedEvent($payment);

        // Pretty-printed: same data, different bytes to json_encode($payload).
        $raw = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->handleWebhook($payload, rawOverride: $raw);

        $this->assertSame('completed', $payment->fresh()->status);
    }

    /** Billing totals are minor-unit strings; an underpaid transaction must not activate. */
    public function test_an_underpaid_transaction_is_rejected(): void
    {
        $this->gateway();
        $payment = $this->pendingPayment();

        $this->handleWebhook($this->completedEvent($payment, total: '1000'));

        $this->assertSame('pending', $payment->fresh()->status);
    }

    /**
     * A zero-decimal currency has no minor unit. Dividing ¥5000 by 100 read a
     * correctly-paid order as ¥50 and rejected it as underpaid.
     */
    public function test_a_zero_decimal_currency_is_not_divided(): void
    {
        $this->gateway();
        $payment = $this->pendingPayment(5000);
        $payment->update(['currency' => 'JPY']);

        $this->handleWebhook($this->completedEvent($payment, total: '5000', currency: 'JPY'));

        $this->assertSame('completed', $payment->fresh()->status);
    }

    public function test_a_currency_mismatch_is_rejected(): void
    {
        $this->gateway();
        $payment = $this->pendingPayment();

        $this->handleWebhook($this->completedEvent($payment, currency: 'EUR'));

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_a_failed_payment_marks_the_payment_failed(): void
    {
        $this->gateway();
        $payment = $this->pendingPayment();

        $this->handleWebhook([
            'event_type' => 'transaction.payment_failed',
            'data' => ['id' => 'txn_01ABC', 'custom_data' => ['payment_ulid' => $payment->ulid]],
        ]);

        $this->assertSame('failed', $payment->fresh()->status);
    }

    /** Refunds arrive as approved adjustments, not as an event on the transaction. */
    public function test_an_approved_refund_adjustment_refunds_the_payment(): void
    {
        $this->gateway();
        $payment = $this->pendingPayment();
        $this->handleWebhook($this->completedEvent($payment));
        $this->assertSame('completed', $payment->fresh()->status);

        $this->handleWebhook([
            'event_type' => 'adjustment.updated',
            'data' => [
                'id' => 'adj_01', 'action' => 'refund', 'status' => 'approved',
                'transaction_id' => 'txn_01ABC',
            ],
        ]);

        $this->assertSame('refunded', $payment->fresh()->status);
    }

    /** A pending (unapproved) refund request must not revoke access yet. */
    public function test_a_pending_refund_adjustment_is_ignored(): void
    {
        $this->gateway();
        $payment = $this->pendingPayment();
        $this->handleWebhook($this->completedEvent($payment));

        $this->handleWebhook([
            'event_type' => 'adjustment.created',
            'data' => [
                'id' => 'adj_01', 'action' => 'refund', 'status' => 'pending_approval',
                'transaction_id' => 'txn_01ABC',
            ],
        ]);

        $this->assertSame('completed', $payment->fresh()->status);
    }

    /** Redelivery is expected; the second one must not re-activate. */
    public function test_a_redelivered_event_is_idempotent(): void
    {
        $this->gateway();
        $payment = $this->pendingPayment();

        $this->handleWebhook($this->completedEvent($payment));
        $this->handleWebhook($this->completedEvent($payment));

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertDatabaseCount('billing_subscriptions', 1);
    }
}
