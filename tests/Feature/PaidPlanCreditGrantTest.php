<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\Subscription\SubscriptionLifecycleService;
use Tests\TestCase;

/**
 * Paid-plan activation ADDS the plan's credits to the user's wallet.
 *
 * This class previously asserted the opposite: activation topped the wallet UP TO the
 * plan figure, on the rule that a plan guarantees at least its allowance. In practice
 * that spent the buyer's existing balance on their own purchase — 23 credits plus a
 * 10,000-credit plan came to 10,000, not 10,023 — and someone already above the plan
 * figure received nothing at all for subscribing. Reported from production, and the rule
 * changed: a plan adds its credits.
 *
 * The top-up-to behaviour still exists for the anniversary refresh
 * (User::grantPlanAllowance), where not compounding is the whole point. See
 * PlanCreditGrantTest for both halves side by side.
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

    public function test_activation_adds_plan_credits_to_the_existing_balance(): void
    {
        $plan = $this->plan(10000);
        $user = User::factory()->create(['credits' => 100]); // signup default
        $payment = $this->pendingPayment($plan, $user);

        app(SubscriptionLifecycleService::class)->activateFromPayment($payment, 'pay_1', 'sub_1');

        $this->assertEquals(10100.0, (float) $user->fresh()->credits,
            'The signup credits were the buyer\'s already; the plan adds to them.');
    }

    /**
     * The worst case under the old rule: a buyer holding more than the plan includes
     * received nothing whatsoever in exchange for subscribing.
     */
    public function test_activation_grants_in_full_even_when_the_balance_already_exceeds_the_plan(): void
    {
        $plan = $this->plan(2000);
        $user = User::factory()->create(['credits' => 5000]); // has top-ups above allowance
        $payment = $this->pendingPayment($plan, $user);

        app(SubscriptionLifecycleService::class)->activateFromPayment($payment, 'pay_2', 'sub_2');

        $this->assertEquals(7000.0, (float) $user->fresh()->credits,
            'Paid top-ups are the buyer\'s money and must survive a subscription.');
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
