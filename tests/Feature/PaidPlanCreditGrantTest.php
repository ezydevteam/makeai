<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\Subscription\SubscriptionLifecycleService;
use Tests\TestCase;

/**
 * Paid-plan activation grants the plan's credit allowance to the user's wallet,
 * refreshing a spent-down balance without wiping top-ups.
 */
class PaidPlanCreditGrantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Grant path drains/credits broadcast to Reverb — mute it in tests.
        $this->instance(
            \App\Services\NotificationEventService::class,
            \Mockery::mock(\App\Services\NotificationEventService::class)->shouldIgnoreMissing(),
        );
    }

    private function plan(int $credits = 10000): Plan
    {
        return Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 50, 'price_yearly' => 500, 'vat_percentage' => 0,
            'credits' => $credits, 'is_active' => true, 'is_free' => false, 'sort_order' => 1,
        ]);
    }

    private function pendingPayment(Plan $plan, User $user): Payment
    {
        return Payment::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'gateway' => 'stripe',
            'amount' => 50,
            'currency' => 'USD',
            'status' => 'pending',
            'type' => 'subscription',
            'metadata' => ['billing_cycle' => 'monthly'],
        ]);
    }

    public function test_activation_grants_plan_credits(): void
    {
        $plan = $this->plan(10000);
        $user = User::factory()->create(['credits' => 100]); // signup default
        $payment = $this->pendingPayment($plan, $user);

        app(SubscriptionLifecycleService::class)->activateFromPayment($payment, 'pay_1', 'sub_1');

        $this->assertEquals(10000.0, (float) $user->fresh()->credits, 'Wallet bumped up to plan credits on activation.');
    }

    public function test_activation_preserves_higher_topup_balance(): void
    {
        $plan = $this->plan(2000);
        $user = User::factory()->create(['credits' => 5000]); // has top-ups above allowance
        $payment = $this->pendingPayment($plan, $user);

        app(SubscriptionLifecycleService::class)->activateFromPayment($payment, 'pay_2', 'sub_2');

        $this->assertEquals(5000.0, (float) $user->fresh()->credits, 'Balance above the allowance is preserved.');
    }

    public function test_grant_is_logged_as_purchase_transaction(): void
    {
        $plan = $this->plan(3000);
        $user = User::factory()->create(['credits' => 0]);
        $payment = $this->pendingPayment($plan, $user);

        app(SubscriptionLifecycleService::class)->activateFromPayment($payment, 'pay_3', 'sub_3');

        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'type' => 'purchase',
            'amount' => 3000,
        ]);
    }
}
