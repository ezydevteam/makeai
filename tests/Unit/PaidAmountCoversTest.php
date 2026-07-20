<?php

namespace Tests\Unit;

use App\Jobs\ProcessPaymentWebhookJob;
use App\Models\Payment;
use App\Models\User;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Findings #2/#3 — the shared amount guard: a signature can prove authenticity
 * without binding the amount, so activation must confirm the paid amount covers
 * what we expected to charge.
 */
class PaidAmountCoversTest extends TestCase
{
    private function covers(float $paid, ?string $currency, float $expected = 50): bool
    {
        $payment = Payment::create([
            'user_id' => User::factory()->create()->id,
            'gateway' => 'coingate',
            'amount' => $expected,
            'currency' => 'USD',
            'status' => 'pending',
            'type' => 'subscription',
        ]);

        $method = new ReflectionMethod(ProcessPaymentWebhookJob::class, 'paidAmountCovers');
        $method->setAccessible(true);

        return $method->invoke(new ProcessPaymentWebhookJob('coingate', []), $payment, $paid, $currency);
    }

    public function test_exact_amount_passes(): void
    {
        $this->assertTrue($this->covers(50.0, 'USD'));
    }

    public function test_overpayment_passes(): void
    {
        $this->assertTrue($this->covers(60.0, 'USD'));
    }

    public function test_underpayment_is_rejected(): void
    {
        $this->assertFalse($this->covers(1.0, 'USD'));
    }

    public function test_currency_mismatch_is_rejected(): void
    {
        $this->assertFalse($this->covers(50.0, 'EUR'));
    }

    public function test_absent_amount_is_rejected_when_we_expected_to_be_paid(): void
    {
        // Previously waved through as "unknown", which defeated the whole guard: a payload
        // that simply omitted the amount field activated the plan. If we expected $50 and
        // the gateway reports nothing, that is not a confirmed payment.
        $this->assertFalse($this->covers(0.0, null));
    }

    public function test_absent_amount_passes_when_nothing_was_owed(): void
    {
        // A fully-discounted order has nothing to verify. (In practice a zero-amount
        // checkout is activated directly and never reaches a gateway, but the guard must
        // not block one if it ever did.)
        $this->assertTrue($this->covers(0.0, null, expected: 0));
    }

    public function test_penny_rounding_is_tolerated(): void
    {
        $this->assertTrue($this->covers(49.995, 'USD'));
    }
}
