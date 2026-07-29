<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\GatewaySubscription;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cancelling a subscription from the admin panel must take back the plan's credits.
 *
 * The immediate-cancel branch clears plan_id by hand rather than going through
 * SubscriptionLifecycleService::expireNow(), so it never reached the one place that
 * strips the allowance. The user lost the plan and kept the entire balance — an admin
 * cancelling a 50k-credit subscription left the account holding 50k of spendable credit
 * with no plan behind it and no further payments coming.
 *
 * Credits the user separately PAID for are not the plan's to take back, so those survive.
 */
class AdminSubscriptionCancelCreditsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        settings_set('license_type', '2', 'integer', 'license');
        $this->withoutMiddleware(\App\Http\Middleware\LicenseMiddleware::class);
    }

    private function admin(): Admin
    {
        $role = AdminRole::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);

        return Admin::firstOrCreate(
            ['email' => 'root@example.com'],
            ['name' => 'Root', 'password' => 'password', 'role_id' => $role->id, 'is_active' => true],
        );
    }

    private function plan(int $credits = 50000): Plan
    {
        return Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 50, 'price_yearly' => 500,
            'vat_percentage' => 0, 'credits' => $credits,
            'is_active' => true, 'is_free' => false, 'sort_order' => 2,
        ]);
    }

    /** @param array{credits:float,topup_credits:float} $wallet */
    private function subscribedUser(Plan $plan, array $wallet): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
            'plan_id' => $plan->id,
            'subscription_status' => 'active',
            'subscription_ends_at' => now()->addMonth(),
            'credits' => $wallet['credits'],
            'topup_credits' => $wallet['topup_credits'],
        ]);

        GatewaySubscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'status' => GatewaySubscription::STATUS_ACTIVE,
            'gateway' => 'manual',
            'amount' => 50,
            'currency' => 'USD',
            'current_period_start' => now()->subDays(3),
            'current_period_end' => now()->addMonth(),
        ]);

        return $user;
    }

    private function cancel(User $user, string $mode = 'immediate'): \Illuminate\Testing\TestResponse
    {
        // Bound by ulid, not id — route() resolves that from the model.
        return $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.subscriptions.deactivate', $user), ['mode' => $mode]);
    }

    public function test_an_immediate_cancel_takes_back_the_plan_credits(): void
    {
        $user = $this->subscribedUser($this->plan(50000), ['credits' => 50000, 'topup_credits' => 0]);

        $this->cancel($user)->assertRedirect()->assertSessionHas('success');

        $user->refresh();
        $this->assertSame(0.0, (float) $user->credits);
        $this->assertNull($user->plan_id);
        $this->assertSame('none', $user->subscription_status);
    }

    /** The user's own money is not the plan's to take back. */
    public function test_purchased_top_up_credits_survive_the_cancellation(): void
    {
        // 50k plan allowance + 5k they bought separately.
        $user = $this->subscribedUser($this->plan(50000), ['credits' => 55000, 'topup_credits' => 5000]);

        $this->cancel($user);

        $this->assertSame(5000.0, (float) $user->fresh()->credits);
        $this->assertSame(5000.0, (float) $user->fresh()->topup_credits);
    }

    /** A partly-spent wallet still lands on exactly the top-up balance, never negative. */
    public function test_a_partly_spent_wallet_settles_at_the_top_up_balance(): void
    {
        // Spent down to 2,000 — below what they had topped up.
        $user = $this->subscribedUser($this->plan(50000), ['credits' => 2000, 'topup_credits' => 5000]);

        $this->cancel($user);

        // Restored UP to the protected top-up: those credits were paid for and unspent
        // plan credits were being burned first.
        $this->assertSame(5000.0, (float) $user->fresh()->credits);
    }

    /** The revocation is recorded, not silent — the balance change must be auditable. */
    public function test_the_revocation_is_written_to_the_credit_ledger(): void
    {
        $user = $this->subscribedUser($this->plan(50000), ['credits' => 50000, 'topup_credits' => 0]);

        $this->cancel($user);

        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'amount' => -50000,
            'balance_after' => 0,
        ]);
    }

    /**
     * A period-end cancel leaves the user paid up until their period ends, so the credits
     * they already paid for stay until it does.
     */
    public function test_a_period_end_cancel_leaves_the_credits_alone(): void
    {
        $user = $this->subscribedUser($this->plan(50000), ['credits' => 50000, 'topup_credits' => 0]);

        $this->cancel($user, 'period_end');

        $this->assertSame(50000.0, (float) $user->fresh()->credits);
    }

    /**
     * An admin-granted plan has no subscription row; the controller synthesises one, so
     * the allowance must be revoked there too.
     */
    public function test_an_admin_granted_plan_without_a_subscription_also_loses_its_credits(): void
    {
        $plan = $this->plan(10000);
        $user = User::factory()->create([
            'is_active' => true, 'email_verified_at' => now(),
            'plan_id' => $plan->id, 'subscription_status' => 'active',
            'credits' => 10000, 'topup_credits' => 0,
        ]);

        $this->cancel($user);

        $this->assertSame(0.0, (float) $user->fresh()->credits);
    }

    /**
     * Nothing to revoke means nothing is taken. Zeroing here would confiscate free-tier
     * balance and admin grants from someone who never had a subscription at all.
     */
    public function test_a_user_with_no_plan_or_subscription_keeps_their_balance(): void
    {
        $user = User::factory()->create([
            'is_active' => true, 'email_verified_at' => now(),
            'plan_id' => null, 'subscription_status' => 'none',
            'credits' => 250, 'topup_credits' => 0,
        ]);

        $this->cancel($user);

        $this->assertSame(250.0, (float) $user->fresh()->credits);
    }
}
