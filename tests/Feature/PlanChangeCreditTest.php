<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Changing plans swaps the allowance; it does not buy a second one.
 *
 * Reported from production: a 10k → 50k upgrade on a wallet holding 10k plan credits and
 * a 3.5k top-up produced 63.5k. The old plan's unused credits were paid for twice — once
 * in the proration discount on the price, and again in the wallet — and repeated up/down
 * changes stacked an allowance every time. The right answer is 53.5k: the new plan plus
 * the credits the user actually bought.
 *
 * The mirror case was worse: a downgrade granted nothing at all, so 50k → 10k kept the
 * whole 50k. It still grants nothing at the moment the downgrade APPLIES — no money
 * changes hands at period end, and a one-time plan is never billed again — the wallet
 * follows the plan on the next successful recurring payment instead.
 */
class PlanChangeCreditTest extends TestCase
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

    private function user(float $credits, float $topups = 0): User
    {
        return User::factory()->create([
            'is_active' => true,
            'credits' => $credits,
            'topup_credits' => $topups,
        ]);
    }

    /** The exact case from production. */
    public function test_an_upgrade_replaces_the_old_allowance_and_keeps_the_topup(): void
    {
        $user = $this->user(13500, 3500);   // 10k plan left + 3.5k bought

        $user->resetPlanAllowance(50000, 'Plan changed to Professional');

        $this->assertSame(53500.0, (float) $user->fresh()->credits);
        $this->assertSame(3500.0, (float) $user->fresh()->topup_credits, 'purchased credits are never part of the swap');
    }

    /** A downgrade has to be able to move the balance DOWN — that is the whole point. */
    public function test_a_downgrade_reduces_the_allowance_but_not_the_topup(): void
    {
        $user = $this->user(53500, 3500);   // 50k plan + 3.5k bought

        $user->resetPlanAllowance(10000, 'Plan renewal credits: Starter');

        $this->assertSame(13500.0, (float) $user->fresh()->credits);
        $this->assertSame(3500.0, (float) $user->fresh()->topup_credits);
    }

    public function test_a_reduction_is_recorded_in_the_ledger_as_an_adjustment(): void
    {
        $user = $this->user(53500, 3500);

        $user->resetPlanAllowance(10000, 'Plan renewal credits: Starter');

        $entry = $user->creditTransactions()->latest('id')->first();

        $this->assertSame(-40000.0, (float) $entry->amount);
        $this->assertSame(13500.0, (float) $entry->balance_after);
        $this->assertSame('admin_adjust', $entry->type, 'not a refund — no money goes back');
    }

    public function test_an_increase_is_recorded_as_a_purchase(): void
    {
        $user = $this->user(13500, 3500);

        $user->resetPlanAllowance(50000, 'Plan changed to Professional');

        $entry = $user->creditTransactions()->latest('id')->first();

        $this->assertSame(40000.0, (float) $entry->amount);
        $this->assertSame('purchase', $entry->type);
    }

    /** Repeated changes must not accumulate — the exploit the old additive path allowed. */
    public function test_switching_back_and_forth_does_not_stack_allowances(): void
    {
        $user = $this->user(10000);

        $user->resetPlanAllowance(50000, 'up');
        $user->resetPlanAllowance(10000, 'down');
        $user->resetPlanAllowance(50000, 'up again');

        $this->assertSame(50000.0, (float) $user->fresh()->credits);
    }

    /** Already at the right figure: no wallet change and no noise in the ledger. */
    public function test_no_ledger_entry_when_the_balance_is_already_correct(): void
    {
        $user = $this->user(50000);

        $user->resetPlanAllowance(50000, 'Plan renewal credits: Professional');

        $this->assertSame(0, $user->creditTransactions()->count());
    }

    /** A spent-down wallet is topped back to the full allowance on renewal. */
    public function test_a_renewal_restores_a_spent_down_wallet(): void
    {
        $user = $this->user(1200, 0);

        $user->resetPlanAllowance(10000, 'Plan renewal credits: Starter');

        $this->assertSame(10000.0, (float) $user->fresh()->credits);
    }
}
