<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaymentWebhookJob;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use App\Services\Payment\PaymentActivationService;
use App\Services\Subscription\SubscriptionLifecycleService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The happy path for every non-Stripe gateway webhook: a correctly-signed
 * "payment succeeded" notification must activate the pending subscription
 * (payment → completed, user granted the plan). Signatures are computed the
 * real way each gateway does it, so a broken verifier fails the test rather
 * than passing on a forged/blank signature.
 *
 * Stripe's webhook path is covered separately (PaymentAuditFixesTest).
 * Underpayment negatives are covered by WebhookAmountVerificationTest.
 */
class GatewayWebhookActivationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);
    }

    private function handle(string $gateway, array $payload, string $rawBody = '', array $headers = []): void
    {
        (new ProcessPaymentWebhookJob($gateway, $payload, $rawBody, $headers))->handle(
            app(SubscriptionLifecycleService::class),
            app(PaymentActivationService::class),
        );
    }

    private function pendingPayment(string $gateway, array $metadata = [], float $amount = 50): Payment
    {
        $plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro-'.uniqid(),
            'price_monthly' => $amount,
            'price_yearly' => $amount * 10,
            'vat_percentage' => 0,
            'credits' => 1000,
            'is_active' => true,
            'is_free' => false,
            'sort_order' => 2,
        ]);

        return Payment::create([
            'user_id' => User::factory()->create(['is_active' => true])->id,
            'plan_id' => $plan->id,
            'gateway' => $gateway,
            'amount' => $amount,
            'currency' => 'USD',
            'status' => 'pending',
            'type' => 'subscription',
            'metadata' => array_merge(['billing_cycle' => 'monthly'], $metadata),
        ]);
    }

    private function assertActivated(Payment $payment): void
    {
        $payment->refresh();
        $this->assertSame('completed', $payment->status);
        $this->assertSame((int) $payment->plan_id, (int) $payment->user->fresh()->plan_id);
        $this->assertDatabaseHas('billing_subscriptions', [
            'user_id' => $payment->user_id,
            'plan_id' => $payment->plan_id,
            'status' => 'active',
        ]);
    }

    // ─── Razorpay (HMAC-SHA256 over the raw body) ──

    public function test_razorpay_payment_link_paid_activates_with_a_valid_signature(): void
    {
        PaymentGateway::create([
            'slug' => 'razorpay', 'name' => 'Razorpay', 'is_enabled' => true,
            'credentials' => PaymentGateway::encryptCredentials(['webhook_secret' => 'whsec']),
        ]);
        $payment = $this->pendingPayment('razorpay');

        $payload = [
            'event' => 'payment_link.paid',
            'payload' => [
                'payment_link' => ['entity' => ['reference_id' => $payment->ulid]],
                'payment' => ['entity' => ['id' => 'pay_123']],
            ],
        ];
        $raw = json_encode($payload);
        $sig = hash_hmac('sha256', $raw, 'whsec');

        $this->handle('razorpay', $payload, $raw, ['x-razorpay-signature' => $sig]);

        $this->assertActivated($payment);
    }

    public function test_razorpay_rejects_a_bad_signature(): void
    {
        PaymentGateway::create([
            'slug' => 'razorpay', 'name' => 'Razorpay', 'is_enabled' => true,
            'credentials' => PaymentGateway::encryptCredentials(['webhook_secret' => 'whsec']),
        ]);
        $payment = $this->pendingPayment('razorpay');
        $payload = ['event' => 'payment_link.paid', 'payload' => ['payment_link' => ['entity' => ['reference_id' => $payment->ulid]]]];
        $raw = json_encode($payload);

        $this->handle('razorpay', $payload, $raw, ['x-razorpay-signature' => 'wrong']);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    // ─── Paystack (HMAC-SHA512 over the raw body) ──

    public function test_paystack_charge_success_activates_with_a_valid_signature(): void
    {
        PaymentGateway::create([
            'slug' => 'paystack', 'name' => 'Paystack', 'is_enabled' => true,
            'credentials' => PaymentGateway::encryptCredentials(['secret_key' => 'sk_test']),
        ]);
        $payment = $this->pendingPayment('paystack');

        // amount is minor units — pendingPayment() bills $50.
        $payload = ['event' => 'charge.success', 'data' => [
            'reference' => $payment->ulid, 'id' => 'ps_1', 'amount' => 5000, 'currency' => 'USD',
        ]];
        $raw = json_encode($payload);
        $sig = hash_hmac('sha512', $raw, 'sk_test');

        $this->handle('paystack', $payload, $raw, ['x-paystack-signature' => $sig]);

        $this->assertActivated($payment);
    }

    /**
     * A correctly-signed notification for less than we billed must not activate.
     *
     * The signature proves the message came from Paystack; it says nothing about whether
     * the figure inside matches the order. This guard used to exist only on the
     * confirm-on-return path, so the two disagreed about what counted as paid.
     */
    public function test_paystack_rejects_an_underpaid_charge(): void
    {
        PaymentGateway::create([
            'slug' => 'paystack', 'name' => 'Paystack', 'is_enabled' => true,
            'credentials' => PaymentGateway::encryptCredentials(['secret_key' => 'sk_test']),
        ]);
        $payment = $this->pendingPayment('paystack');

        $payload = ['event' => 'charge.success', 'data' => [
            'reference' => $payment->ulid, 'id' => 'ps_1', 'amount' => 100, 'currency' => 'USD',
        ]];
        $raw = json_encode($payload);

        $this->handle('paystack', $payload, $raw, ['x-paystack-signature' => hash_hmac('sha512', $raw, 'sk_test')]);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    /** A zero-decimal currency has no minor unit — ¥5000 is ¥5000, not ¥50. */
    public function test_paystack_does_not_divide_a_zero_decimal_currency(): void
    {
        PaymentGateway::create([
            'slug' => 'paystack', 'name' => 'Paystack', 'is_enabled' => true,
            'credentials' => PaymentGateway::encryptCredentials(['secret_key' => 'sk_test']),
        ]);
        $payment = $this->pendingPayment('paystack', amount: 5000);
        $payment->update(['currency' => 'JPY']);

        $payload = ['event' => 'charge.success', 'data' => [
            'reference' => $payment->ulid, 'id' => 'ps_1', 'amount' => 5000, 'currency' => 'JPY',
        ]];
        $raw = json_encode($payload);

        $this->handle('paystack', $payload, $raw, ['x-paystack-signature' => hash_hmac('sha512', $raw, 'sk_test')]);

        $this->assertActivated($payment);
    }

    public function test_paystack_rejects_a_bad_signature(): void
    {
        PaymentGateway::create([
            'slug' => 'paystack', 'name' => 'Paystack', 'is_enabled' => true,
            'credentials' => PaymentGateway::encryptCredentials(['secret_key' => 'sk_test']),
        ]);
        $payment = $this->pendingPayment('paystack');
        $payload = ['event' => 'charge.success', 'data' => ['reference' => $payment->ulid, 'id' => 'ps_1']];

        $this->handle('paystack', $payload, json_encode($payload), ['x-paystack-signature' => 'nope']);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    // Paddle is covered by PaddleBillingTest — it is the one gateway here whose webhook
    // signs the RAW body rather than the parsed fields, so it needs that file's harness.

    // ─── CoinGate (per-order token + amount) ───────

    public function test_coingate_paid_activates_with_the_right_token_and_amount(): void
    {
        PaymentGateway::create(['slug' => 'coingate', 'name' => 'CoinGate', 'is_enabled' => true]);
        $payment = $this->pendingPayment('coingate', ['coingate_webhook_token' => 'tok_secret']);

        $this->handle('coingate', [
            'order_id' => $payment->ulid,
            'token' => 'tok_secret',
            'status' => 'paid',
            'id' => 'cg_1',
            'price_amount' => 50,
            'price_currency' => 'USD',
        ]);

        $this->assertActivated($payment);
    }

    public function test_coingate_rejects_a_wrong_token(): void
    {
        PaymentGateway::create(['slug' => 'coingate', 'name' => 'CoinGate', 'is_enabled' => true]);
        $payment = $this->pendingPayment('coingate', ['coingate_webhook_token' => 'tok_secret']);

        $this->handle('coingate', [
            'order_id' => $payment->ulid, 'token' => 'tok_WRONG', 'status' => 'paid',
            'id' => 'cg_1', 'price_amount' => 50, 'price_currency' => 'USD',
        ]);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    // ─── 2Checkout (INS md5 hash + amount) ─────────

    public function test_twocheckout_approved_activates_with_a_valid_hash_and_amount(): void
    {
        PaymentGateway::create([
            'slug' => '2checkout', 'name' => '2Checkout', 'is_enabled' => true,
            'credentials' => PaymentGateway::encryptCredentials(['secret_key' => 'secretword']),
        ]);
        $payment = $this->pendingPayment('2checkout');
        $hash = strtoupper(md5('s1'.'v1'.'i1'.'secretword'));

        $this->handle('2checkout', [
            'message_type' => 'ORDER_CREATED',
            'vendor_order_id' => $payment->ulid,
            'sale_id' => 's1', 'vendor_id' => 'v1', 'invoice_id' => 'i1',
            'invoice_status' => 'approved',
            'invoice_list_amount' => 50, 'list_currency' => 'USD',
            'md5_hash' => $hash,
        ]);

        $this->assertActivated($payment);
    }

    // ─── SSLCommerz (server-side validator call) ───

    public function test_sslcommerz_valid_activates_after_validator_confirms_the_amount(): void
    {
        PaymentGateway::create([
            'slug' => 'sslcommerz', 'name' => 'SSLCommerz', 'is_enabled' => true, 'is_test_mode' => true,
            'credentials' => PaymentGateway::encryptCredentials(['store_id' => 'store', 'store_password' => 'pass']),
        ]);
        $payment = $this->pendingPayment('sslcommerz');

        Http::fake([
            'sandbox.sslcommerz.com/validator/*' => Http::response([
                'status' => 'VALID',
                'tran_id' => $payment->ulid,
                'currency_amount' => 50,
                'currency_type' => 'USD',
            ]),
        ]);

        $this->handle('sslcommerz', [
            'tran_id' => $payment->ulid,
            'status' => 'VALID',
            'val_id' => 'val_1',
            'bank_tran_id' => 'bank_1',
        ]);

        $this->assertActivated($payment);
    }

    public function test_sslcommerz_rejects_when_the_validator_reports_an_underpayment(): void
    {
        PaymentGateway::create([
            'slug' => 'sslcommerz', 'name' => 'SSLCommerz', 'is_enabled' => true, 'is_test_mode' => true,
            'credentials' => PaymentGateway::encryptCredentials(['store_id' => 'store', 'store_password' => 'pass']),
        ]);
        $payment = $this->pendingPayment('sslcommerz');

        Http::fake([
            'sandbox.sslcommerz.com/validator/*' => Http::response([
                'status' => 'VALID', 'tran_id' => $payment->ulid,
                'currency_amount' => 1, 'currency_type' => 'USD',
            ]),
        ]);

        $this->handle('sslcommerz', ['tran_id' => $payment->ulid, 'status' => 'VALID', 'val_id' => 'val_1']);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    // ─── PayPal (one-time capture, signature verified via API) ──

    public function test_paypal_capture_completed_activates_with_a_verified_signature(): void
    {
        PaymentGateway::create([
            'slug' => 'paypal', 'name' => 'PayPal', 'is_enabled' => true, 'is_test_mode' => true,
            'credentials' => PaymentGateway::encryptCredentials([
                'client_id' => 'cid', 'client_secret' => 'csec', 'webhook_id' => 'wh_1',
            ]),
        ]);
        $payment = $this->pendingPayment('paypal');

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        ]);

        $payload = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'cap_1', 'custom_id' => $payment->ulid, 'amount' => ['value' => '50.00', 'currency_code' => 'USD']],
        ];

        $this->handle('paypal', $payload, json_encode($payload), [
            'paypal-auth-algo' => 'SHA256withRSA',
            'paypal-transmission-id' => 't1',
            'paypal-transmission-sig' => 's1',
            'paypal-transmission-time' => '2026-01-01T00:00:00Z',
            'paypal-cert-url' => 'https://example.test/cert',
        ]);

        $this->assertActivated($payment);
    }

    public function test_paypal_rejects_when_signature_verification_fails(): void
    {
        PaymentGateway::create([
            'slug' => 'paypal', 'name' => 'PayPal', 'is_enabled' => true, 'is_test_mode' => true,
            'credentials' => PaymentGateway::encryptCredentials([
                'client_id' => 'cid', 'client_secret' => 'csec', 'webhook_id' => 'wh_1',
            ]),
        ]);
        $payment = $this->pendingPayment('paypal');

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'FAILURE']),
        ]);

        $payload = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['id' => 'cap_1', 'custom_id' => $payment->ulid],
        ];

        $this->handle('paypal', $payload, json_encode($payload), ['paypal-auth-algo' => 'x', 'paypal-transmission-id' => 't']);

        $this->assertSame('pending', $payment->fresh()->status);
    }
}
