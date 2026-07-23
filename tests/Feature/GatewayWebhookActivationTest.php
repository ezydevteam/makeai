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

        $payload = ['event' => 'charge.success', 'data' => ['reference' => $payment->ulid, 'id' => 'ps_1']];
        $raw = json_encode($payload);
        $sig = hash_hmac('sha512', $raw, 'sk_test');

        $this->handle('paystack', $payload, $raw, ['x-paystack-signature' => $sig]);

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

    // ─── Paddle (Classic RSA p_signature) ──────────

    public function test_paddle_payment_succeeded_activates_with_a_valid_rsa_signature(): void
    {
        [$publicKey, $privateKey] = $this->rsaKeypair();
        PaymentGateway::create([
            'slug' => 'paddle', 'name' => 'Paddle', 'is_enabled' => true,
            'credentials' => PaymentGateway::encryptCredentials(['public_key' => $publicKey]),
        ]);
        $payment = $this->pendingPayment('paddle');

        $fields = [
            'alert_name' => 'payment_succeeded',
            'order_id' => 'ord_1',
            'passthrough' => json_encode(['payment_ulid' => $payment->ulid]),
        ];
        $fields['p_signature'] = $this->paddleSignature($fields, $privateKey);

        $this->handle('paddle', $fields);

        $this->assertActivated($payment);
    }

    public function test_paddle_rejects_a_forged_signature(): void
    {
        [$publicKey] = $this->rsaKeypair();
        PaymentGateway::create([
            'slug' => 'paddle', 'name' => 'Paddle', 'is_enabled' => true,
            'credentials' => PaymentGateway::encryptCredentials(['public_key' => $publicKey]),
        ]);
        $payment = $this->pendingPayment('paddle');

        $this->handle('paddle', [
            'alert_name' => 'payment_succeeded',
            'order_id' => 'ord_1',
            'passthrough' => json_encode(['payment_ulid' => $payment->ulid]),
            'p_signature' => base64_encode('forged'),
        ]);

        $this->assertSame('pending', $payment->fresh()->status);
    }

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

    // ─── helpers ─────────────────────────────────

    /**
     * A fixed test RSA keypair. Generated once with `openssl genrsa 2048` and embedded
     * so the suite does not depend on openssl_pkey_new() finding an openssl.cnf (it
     * cannot on a bare Windows/Laragon PHP). Deriving the public key and signing from a
     * loaded PEM need no config.
     *
     * @return array{0:string,1:\OpenSSLAsymmetricKey} [publicPem, privateKey]
     */
    private function rsaKeypair(): array
    {
        $privatePem = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEuwIBADANBgkqhkiG9w0BAQEFAASCBKUwggShAgEAAoIBAQCglN8/OB/5K7tp
H+qfB6L16HHNnNYESpgEwc577k3VCOt8g5n39dOBLY95HqXTYgOp34/clgVRqqfv
cTCx+UNds7LyiFNNX5RzaBgCW0L5M1Yg32CxKb4J7v2DoaTnYiFRPEWDz2pM54Ml
42gnnrjgiCssIqrB88JGixh4u9cdH6OznYnVRzU4lJWEKmPnAcd8CmuD7d+ZwAtL
x118VmIcB/Txn3AA5orPc9vbO/1P/zMKE1ofTPQGStOFZTZQQBsWNcsVP/cvatRz
i4PAX9JhXtGrr+clNtR4Eb2/h1Z4wIFWQ0V6uXEDmbnUvANBls5fEI9s4zOvHVz8
y2TVyW+RAgMBAAECgf91t1dGAN7EykpDG3fjHLYEOWHeWMU1tXkQrlev11c3Kagh
9Fc/w/WdvMhwVwc47kvBO8yPkr+oyRxSwFHyJcg8kymbTFRvY/cZ+DT22pqTaWQP
X7Es2Rd222ZSGIJ1HHqlZ97jFtSA4TZC5XHKRDtDCI9IIMxiDKSzvJkz6H9z2Oke
VLvym/FM6egtCJHnxxOxzGoXB6HX1fZlkB6QlpxK+yumErlGKnP5lv2AAS+OHKUe
03gbGf41e8OPDPjfjZ0YSJsZVoba9BaHTmM59QTktUcHaKA4VcWNpyRYbXZrWBcw
aR2bElQMPEarlNsmtp7MjyFfAuwK0rUmtmj+A3UCgYEA2esO3WLoAzVZPuATQ0Z0
LvhufIArStUou3dgSggUXVjrlAubZ5N9aiyHM7vAOAlFYFoZ3zvD0JwiCVyQ+Win
74faaFRBCbl3HN7HqLsQPS0PFauCH0LQJj+zca2I+YYCY0Quv1+HL6uhxXqQT99y
7L7qmUTQX6u2FvTrDZFQ3WsCgYEAvKTCthH12mCkjCyVbY5Ya08IdcKV8bOVTbGV
NO2bz7k1qigSE1JHZY9QSPd57PtLDmROKSJqicFEkFqlIi5ES2k2DagQQyL+tRrS
ENvqwfg1INmAjbvN6QeXGLDRm+gX6JjmLse6wdziJDmgTBMnYD6YpNvxsHkQxAUH
lt/bifMCgYBTAGb/B+cbIbzGaA6uNy2VnmZm4WKb9Ci5jrSMPhuTmoTQNMOSZekF
AcTVfZOvREi6dFcaYecpk+6a5jkJ5kTgxTv5NO44x/2Ib4pYyDddNcZjGJpNUeN6
ThUJHXHoqJRMPvIXTkltbNAHKbHB2ngpmGY+zqkXZ43JnKvS1SCZ1QKBgEZcPbOj
J0v4V+dgiat/OENuCv3BQiQQk1OTNM+1ADSOJBH/OB60xaR/u7Y7d+KIKAqKJwz4
pTwUNfqRlJ4XG6n06BBX6xjfaJksE8XuALWwWkCSX3x92+NazWSMLuIzwxciUFiK
boH4XPCd/cfiLQGc11pGHvNvdG1oYthHTp85AoGBANG9B+V6XcReOM8Fr0NXB58m
9+0RoGG23NMvnNjLAuHfuNtJQgw/k9d+f96Dlrvt9QCXJgT4R7KG0FAU03LhaMBn
y5LM9/7UCzA2GJ1/RNY/CsY4nVRVK0Hhpa4I9hoV2D8vgYYFzO+yxwHM4boWcX2n
P4HtR3nOGzla4j6cdahs
-----END PRIVATE KEY-----
PEM;

        $privateKey = openssl_pkey_get_private($privatePem);
        $publicPem = openssl_pkey_get_details($privateKey)['key'];

        return [$publicPem, $privateKey];
    }

    /** Sign Paddle fields exactly as the verifier expects (ksort → stringify → serialize → SHA1). */
    private function paddleSignature(array $fields, \OpenSSLAsymmetricKey $privateKey): string
    {
        ksort($fields);
        foreach ($fields as $k => $v) {
            if (! in_array(gettype($v), ['object', 'array'], true)) {
                $fields[$k] = (string) $v;
            }
        }
        openssl_sign(serialize($fields), $signature, $privateKey, OPENSSL_ALGO_SHA1);

        return base64_encode($signature);
    }
}
