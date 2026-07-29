<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckExtendedLicense;
use App\Http\Middleware\CheckPremium;
use App\Http\Middleware\LicenseMiddleware;
use App\Jobs\ProcessPaymentWebhookJob;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Services\Payment\PaymentActivationService;
use App\Services\Subscription\SubscriptionLifecycleService;
use Tests\TestCase;

/**
 * Credit top-up: pay-as-you-go credit purchases (separate from subscriptions).
 * Covers the credit maths, the minimum/enabled guards, payment creation, and
 * that a gateway "paid" webhook credits the wallet (type = credit_topup path).
 */
class CreditTopupCheckoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([CheckPremium::class, CheckExtendedLicense::class, LicenseMiddleware::class]);
        config(['broadcasting.default' => 'null']);

        Currency::create(['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'exchange_rate' => 1, 'is_default' => true, 'is_active' => true]);
        settings_set('default_currency', 'USD', 'string', 'general');
        settings_set('credit_topup_enabled', true, 'boolean', 'credits');
        settings_set('credit_price_per_unit', 0.01, 'float', 'credits');
        settings_set('credit_topup_minimum', 5, 'float', 'credits');
    }

    private function user(): User
    {
        return User::factory()->create(['is_active' => true, 'email_verified_at' => now(), 'credits' => 100]);
    }

    private function bankGateway(): PaymentGateway
    {
        return PaymentGateway::create([
            'slug' => 'bank_transfer', 'name' => 'Bank Transfer', 'is_enabled' => true,
            'processing_fee_type' => 'none', 'processing_fee_value' => 0, 'sort_order' => 1,
        ]);
    }

    // ─── calculate ───────────────────────────────

    public function test_calculate_returns_base_credits_with_no_float_drift(): void
    {
        // 19.99 / 0.01 must be 1999, not 1998 (the classic floor-after-float-error bug).
        $this->actingAs($this->user())
            ->postJson(route('user.dashboard.credit-topup.calculate'), ['amount' => 19.99])
            ->assertOk()
            ->assertJsonPath('base_credits', 1999)
            ->assertJsonPath('total_credits', 1999);
    }

    public function test_calculate_applies_the_highest_matching_bonus_tier(): void
    {
        settings_set('credit_topup_bonus_tiers', [
            ['min_amount' => 10, 'bonus_percent' => 5],
            ['min_amount' => 50, 'bonus_percent' => 20],
        ], 'json', 'credits');

        $this->actingAs($this->user())
            ->postJson(route('user.dashboard.credit-topup.calculate'), ['amount' => 50])
            ->assertOk()
            ->assertJsonPath('base_credits', 5000)
            ->assertJsonPath('bonus_percent', 20)
            ->assertJsonPath('bonus_credits', 1000)
            ->assertJsonPath('total_credits', 6000);
    }

    // ─── checkout guards ─────────────────────────

    public function test_checkout_below_the_minimum_is_rejected(): void
    {
        $gateway = $this->bankGateway();

        $this->actingAs($this->user())
            ->post(route('user.dashboard.credit-topup.checkout'), ['amount' => 1, 'gateway' => $gateway->slug])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_checkout_is_unavailable_when_topup_is_disabled(): void
    {
        settings_set('credit_topup_enabled', false, 'boolean', 'credits');
        $gateway = $this->bankGateway();

        $this->actingAs($this->user())
            ->post(route('user.dashboard.credit-topup.checkout'), ['amount' => 50, 'gateway' => $gateway->slug])
            ->assertNotFound();
    }

    // ─── checkout creates a credit_topup payment ──

    public function test_checkout_creates_a_pending_credit_topup_payment(): void
    {
        $user = $this->user();
        $gateway = $this->bankGateway();

        $this->actingAs($user)
            ->post(route('user.dashboard.credit-topup.checkout'), ['amount' => 25, 'gateway' => $gateway->slug])
            ->assertRedirect();

        $payment = Payment::where('user_id', $user->id)->where('type', 'credit_topup')->first();
        $this->assertNotNull($payment);
        $this->assertSame('pending', $payment->status);
        $this->assertSame(25.0, (float) $payment->amount);          // no fee on this gateway
        $this->assertSame(2500, (int) $payment->metadata['total_credits']);
    }

    // ─── webhook credits the wallet ──────────────

    public function test_a_paid_webhook_credits_the_wallet_for_a_topup_payment(): void
    {
        PaymentGateway::create([
            'slug' => 'paystack', 'name' => 'Paystack', 'is_enabled' => true,
            'credentials' => PaymentGateway::encryptCredentials(['secret_key' => 'sk_test']),
        ]);
        $user = $this->user(); // starts at 100 credits

        $payment = Payment::create([
            'user_id' => $user->id,
            'gateway' => 'paystack',
            'amount' => 25,
            'currency' => 'USD',
            'status' => 'pending',
            'type' => 'credit_topup',
            'metadata' => ['total_credits' => 2500, 'base_credits' => 2500, 'bonus_credits' => 0],
        ]);

        // amount is minor units — this top-up bills $25. Paystack always sends it, and the
        // webhook now refuses to activate on a charge whose amount it cannot confirm.
        $payload = ['event' => 'charge.success', 'data' => [
            'reference' => $payment->ulid, 'id' => 'ps_1', 'amount' => 2500, 'currency' => 'USD',
        ]];
        $raw = json_encode($payload);
        $sig = hash_hmac('sha512', $raw, 'sk_test');

        (new ProcessPaymentWebhookJob('paystack', $payload, $raw, ['x-paystack-signature' => $sig]))->handle(
            app(SubscriptionLifecycleService::class),
            app(PaymentActivationService::class),
        );

        $this->assertSame('completed', $payment->fresh()->status);
        // 100 starting + 2500 purchased.
        $this->assertSame(2600.0, (float) $user->fresh()->credits);
        // A top-up must never grant a plan.
        $this->assertNull($user->fresh()->plan_id);
    }
}
