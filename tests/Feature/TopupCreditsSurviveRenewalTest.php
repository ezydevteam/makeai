<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Credits a user bought must survive every plan renewal that follows.
 *
 * Plan credits are an allowance: each renewal tops the wallet back up to the plan figure,
 * which is what stops a monthly allowance compounding. Top-ups were being eaten by that
 * same mechanism — a subscriber on 15,000 (10,000 plan + a 5,000 top-up) was already at
 * the allowance, so renewal granted nothing. They spent down, were topped back to 10,000,
 * and over two or three cycles the 5,000 they had paid for quietly vanished. Nobody
 * notices at the time, which is what made it easy to miss.
 *
 * users.topup_credits records how much of the wallet was bought rather than granted, so
 * the renewal target becomes plan credits PLUS that figure.
 */
class TopupCreditsSurviveRenewalTest extends TestCase
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

    private function user(float $credits = 0): User
    {
        return User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
            'credits' => $credits,
        ]);
    }

    public function test_a_topup_is_tracked_separately_from_the_wallet(): void
    {
        $user = $this->user(10000);

        $user->addCredits(5000, 'topup', 'Credit top-up purchase');

        $user->refresh();
        $this->assertSame(15000.0, (float) $user->credits);
        $this->assertSame(5000.0, (float) $user->topup_credits);
    }

    /** Plan grants are an allowance, not a purchase — they must not inflate the figure. */
    public function test_a_plan_grant_does_not_count_as_a_topup(): void
    {
        $user = $this->user(0);

        $user->addCredits(10000, 'purchase', 'Plan credits: Professional');

        $this->assertSame(0.0, (float) $user->fresh()->topup_credits);
    }

    /** The case that was silently losing money. */
    public function test_renewal_tops_up_on_top_of_a_topup_instead_of_absorbing_it(): void
    {
        $user = $this->user(10000);
        $user->addCredits(5000, 'topup', 'Credit top-up purchase');   // 15,000

        $user->deductCredits(3000, 'AI usage');                        // 12,000

        $user->grantPlanAllowance(10000, 'Plan renewal credits: Professional');

        // 10,000 allowance + the 5,000 they bought, not a flat 10,000.
        $this->assertSame(15000.0, (float) $user->fresh()->credits);
    }

    /** Without a top-up the allowance behaves exactly as before: top up to, never beyond. */
    public function test_renewal_without_a_topup_still_tops_up_to_the_plan_figure(): void
    {
        $user = $this->user(2000);

        $user->grantPlanAllowance(10000, 'Plan renewal credits: Professional');

        $this->assertSame(10000.0, (float) $user->fresh()->credits);
    }

    /**
     * Plan credits are spent first. While the balance still covers the top-up, none of the
     * top-up has been touched, so the protected figure must not move.
     */
    public function test_spending_the_plan_portion_leaves_the_protected_figure_intact(): void
    {
        $user = $this->user(10000);
        $user->addCredits(5000, 'topup', 'Credit top-up purchase');   // 15,000

        $user->deductCredits(9000, 'AI usage');                        // 6,000

        $this->assertSame(5000.0, (float) $user->fresh()->topup_credits);
    }

    /**
     * Once the wallet falls below the top-up, the difference really has been spent — and
     * the figure has to follow it down, or renewals would keep reserving room for credits
     * that no longer exist and hand out free ones forever.
     */
    public function test_spending_into_the_topup_reduces_the_protected_figure(): void
    {
        $user = $this->user(10000);
        $user->addCredits(5000, 'topup', 'Credit top-up purchase');   // 15,000

        $user->deductCredits(12000, 'AI usage');                       // 3,000

        $this->assertSame(3000.0, (float) $user->fresh()->topup_credits);

        // Renewal now reserves only what is left of it.
        $user->grantPlanAllowance(10000, 'Plan renewal credits: Professional');

        $this->assertSame(13000.0, (float) $user->fresh()->credits);
    }

    /** A spent-out top-up buyer converges on the plain allowance, not above it. */
    public function test_a_fully_spent_topup_stops_being_reserved(): void
    {
        $user = $this->user(10000);
        $user->addCredits(5000, 'topup', 'Credit top-up purchase');

        $user->deductCredits(15000, 'AI usage');                       // 0

        $this->assertSame(0.0, (float) $user->fresh()->topup_credits);

        $user->grantPlanAllowance(10000, 'Plan renewal credits: Professional');

        $this->assertSame(10000.0, (float) $user->fresh()->credits);
    }
}
