<?php

namespace Tests\Feature;

use App\Models\GatewaySubscription;
use App\Models\Plan;
use App\Models\User;
use App\Services\Subscription\SubscriptionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * When the paid period ends, the plan's credits end with it.
 *
 * Expiry already revoked plan access — plan_id, subscription_status, subscription_ends_at
 * — but never touched the wallet. So a one-time 50k buyer whose period lapsed kept all
 * 50k indefinitely with no plan and no further payments, and a refunded subscriber kept
 * the credits their money had been handed back for. The allowance belongs to the period
 * that paid for it.
 *
 * Purchased top-ups are not part of that: they survive, because they were bought
 * separately and no period covers them.
 */
class PlanExpiryRevokesCreditsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instance(
            \App\Services\NotificationEventService::class,
            \Mockery::mock(\App\Services\NotificationEventService::class)->shouldIgnoreMissing(),
        );
    }

    private function plan(int $credits = 50000): Plan
    {
        return Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 50, 'price_yearly' => 500, 'vat_percentage' => 0,
            'credits' => $credits, 'is_active' => true, 'is_free' => false, 'sort_order' => 1,
        ]);
    }

    private function lapsedSubscription(User $user, Plan $plan): GatewaySubscription
    {
        return GatewaySubscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_'.uniqid(),
            'amount' => 50,
            'currency' => 'USD',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),   // lapsed
        ]);
    }

    public function test_expiry_strips_the_plan_allowance_and_keeps_the_topup(): void
    {
        $plan = $this->plan(50000);
        $user = User::factory()->create([
            'is_active' => true,
            'plan_id' => $plan->id,
            'subscription_status' => 'active',
            'credits' => 53500,        // 50k plan + 3.5k bought
            'topup_credits' => 3500,
        ]);

        $this->lapsedSubscription($user, $plan);

        app(SubscriptionLifecycleService::class)->expirePastDue();

        $user->refresh();
        $this->assertSame(3500.0, (float) $user->credits, 'only the purchased credits remain');
        $this->assertSame(3500.0, (float) $user->topup_credits);
        $this->assertNull($user->plan_id);
        $this->assertSame('none', $user->subscription_status);
    }

    /** No top-up: the wallet empties completely. */
    public function test_expiry_empties_a_wallet_that_was_all_plan_credits(): void
    {
        $plan = $this->plan(50000);
        $user = User::factory()->create([
            'is_active' => true,
            'plan_id' => $plan->id,
            'subscription_status' => 'active',
            'credits' => 42000,
        ]);

        $this->lapsedSubscription($user, $plan);

        app(SubscriptionLifecycleService::class)->expirePastDue();

        $this->assertSame(0.0, (float) $user->fresh()->credits);
    }

    /** The revocation is on the record, so support can see what happened and when. */
    public function test_the_revocation_is_written_to_the_ledger(): void
    {
        $plan = $this->plan(50000);
        $user = User::factory()->create([
            'is_active' => true, 'plan_id' => $plan->id,
            'subscription_status' => 'active', 'credits' => 50000,
        ]);

        $this->lapsedSubscription($user, $plan);

        app(SubscriptionLifecycleService::class)->expirePastDue();

        $entry = $user->creditTransactions()->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertSame(-50000.0, (float) $entry->amount);
        $this->assertStringContainsString('allowance revoked', $entry->description);
    }

    /**
     * The guard that makes this safe: someone holding a second live subscription keeps
     * their balance. Stripping it because an unrelated plan lapsed would be theft.
     */
    public function test_a_user_with_another_live_subscription_keeps_their_credits(): void
    {
        $plan = $this->plan(50000);
        $other = $this->plan(10000);

        $user = User::factory()->create([
            'is_active' => true, 'plan_id' => $plan->id,
            'subscription_status' => 'active', 'credits' => 53500, 'topup_credits' => 3500,
        ]);

        $this->lapsedSubscription($user, $plan);

        GatewaySubscription::create([
            'user_id' => $user->id,
            'plan_id' => $other->id,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_live_'.uniqid(),
            'amount' => 10,
            'currency' => 'USD',
            'current_period_start' => now()->subDays(2),
            'current_period_end' => now()->addMonth(),   // still live
        ]);

        app(SubscriptionLifecycleService::class)->expirePastDue();

        $this->assertSame(53500.0, (float) $user->fresh()->credits);
    }
}
