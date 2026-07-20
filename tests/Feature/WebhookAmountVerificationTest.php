<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaymentWebhookJob;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use App\Services\Payment\PaymentActivationService;
use App\Services\Subscription\SubscriptionLifecycleService;
use Tests\TestCase;

/**
 * Findings #2/#3 — a valid-but-underpaid webhook must NOT activate a plan.
 */
class WebhookAmountVerificationTest extends TestCase
{
    private function handle(string $gateway, array $payload): void
    {
        (new ProcessPaymentWebhookJob($gateway, $payload))->handle(
            app(SubscriptionLifecycleService::class),
            app(PaymentActivationService::class),
        );
    }

    private function pendingPayment(string $gateway, array $metadata = []): Payment
    {
        $plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro-'.uniqid(),
            'price_monthly' => 50,
            'price_yearly' => 500,
            'vat_percentage' => 0,
            'credits' => 1000,
            'is_active' => true,
            'is_free' => false,
            'sort_order' => 1,
        ]);

        return Payment::create([
            'user_id' => User::factory()->create()->id,
            'plan_id' => $plan->id,
            'gateway' => $gateway,
            'amount' => 50,
            'currency' => 'USD',
            'status' => 'pending',
            'type' => 'subscription',
            'metadata' => array_merge(['billing_cycle' => 'monthly'], $metadata),
        ]);
    }

    public function test_coingate_underpayment_does_not_activate(): void
    {
        PaymentGateway::create(['slug' => 'coingate', 'name' => 'CoinGate', 'is_enabled' => true]);
        $payment = $this->pendingPayment('coingate', ['coingate_webhook_token' => 'tok']);

        $this->handle('coingate', [
            'order_id' => $payment->ulid,
            'token' => 'tok',
            'status' => 'paid',
            'id' => 'cg_1',
            'price_amount' => 1,      // paid far less than the $50 expected
            'price_currency' => 'USD',
        ]);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_twocheckout_valid_hash_but_underpaid_does_not_activate(): void
    {
        PaymentGateway::create([
            'slug' => '2checkout',
            'name' => '2Checkout',
            'is_enabled' => true,
            'credentials' => PaymentGateway::encryptCredentials(['secret_key' => 'secret']),
        ]);
        $payment = $this->pendingPayment('2checkout');

        // A correctly-signed INS notification (hash does NOT include the amount)…
        $hash = strtoupper(md5('s1'.'v1'.'i1'.'secret'));

        $this->handle('2checkout', [
            'message_type' => 'ORDER_CREATED',
            'vendor_order_id' => $payment->ulid,
            'sale_id' => 's1',
            'vendor_id' => 'v1',
            'invoice_id' => 'i1',
            'invoice_status' => 'approved',
            'invoice_list_amount' => 1,   // …but the invoiced total is $1, not $50
            'list_currency' => 'USD',
            'md5_hash' => $hash,
        ]);

        $this->assertSame('pending', $payment->fresh()->status);
    }
}
