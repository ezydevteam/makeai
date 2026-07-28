<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Buying a plan must not spend the credits the buyer already had.
 *
 * Subscribing ran through grantPlanAllowance(), which tops the wallet UP TO the plan
 * figure rather than adding to it. A user holding 23 credits who bought a 10,000-credit
 * plan finished on exactly 10,000 — their 23 quietly went toward paying for something
 * they had just paid for. Those credits could have been a purchased top-up or an admin
 * grant; neither has anything to do with a plan allowance the account did not have until
 * that moment.
 *
 * The top-up-to behaviour is still correct for the anniversary refresh, which is what
 * stops a monthly allowance compounding and stops a stalled cron being farmed for a lump
 * sum — so the two grants are now separate methods with separate rules.
 */
class PlanCreditGrantTest extends TestCase
{
    use RefreshDatabase;

    private function user(float $credits): User
    {
        return User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
            'credits' => $credits,
        ]);
    }

    // ─── Purchase grant: additive ──

    public function test_a_purchase_adds_the_plan_credits_to_the_existing_balance(): void
    {
        $user = $this->user(23);

        $user->grantPurchasedPlanCredits(10000, 'Plan credits: Professional');

        $this->assertSame(10023.0, (float) $user->fresh()->credits);
    }

    public function test_a_purchase_grants_the_full_amount_to_an_empty_wallet(): void
    {
        $user = $this->user(0);

        $user->grantPurchasedPlanCredits(10000, 'Plan credits: Professional');

        $this->assertSame(10000.0, (float) $user->fresh()->credits);
    }

    /**
     * The case that made the old behaviour worst: someone who had bought MORE credits
     * than the plan includes received nothing at all for their subscription.
     */
    public function test_a_buyer_already_above_the_plan_figure_still_receives_the_full_grant(): void
    {
        $user = $this->user(12000);

        $user->grantPurchasedPlanCredits(10000, 'Plan credits: Professional');

        $this->assertSame(22000.0, (float) $user->fresh()->credits);
    }

    public function test_a_purchase_is_recorded_in_the_ledger(): void
    {
        $user = $this->user(23);

        $user->grantPurchasedPlanCredits(10000, 'Plan credits: Professional');

        $transaction = $user->creditTransactions()->latest('id')->first();

        $this->assertNotNull($transaction);
        $this->assertSame(10000.0, (float) $transaction->amount);
        $this->assertSame(10023.0, (float) $transaction->balance_after);
    }

    public function test_a_zero_credit_plan_grants_nothing(): void
    {
        $user = $this->user(23);

        $user->grantPurchasedPlanCredits(0, 'Plan credits: Free');

        $this->assertSame(23.0, (float) $user->fresh()->credits);
        $this->assertSame(0, $user->creditTransactions()->count());
    }

    // ─── Refresh grant: still tops up, deliberately ──

    public function test_the_anniversary_refresh_still_tops_up_rather_than_stacking(): void
    {
        $user = $this->user(2000);

        $user->grantPlanAllowance(10000, 'Monthly plan credits: Professional');

        $this->assertSame(10000.0, (float) $user->fresh()->credits,
            'the monthly allowance must not compound, or a stalled cron could be farmed');
    }

    public function test_the_refresh_leaves_a_wallet_above_the_allowance_untouched(): void
    {
        $user = $this->user(12000);

        $user->grantPlanAllowance(10000, 'Monthly plan credits: Professional');

        $this->assertSame(12000.0, (float) $user->fresh()->credits);
    }
}
