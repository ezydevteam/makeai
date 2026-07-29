<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The usage page's two credit bars.
 *
 * The sidebar reports one aggregate wallet; this page separates it, because the two halves
 * behave differently — the plan allowance is refreshed each period, a top-up is the user's
 * own money and survives. Two rules hold the display together:
 *
 *  1. Plan usage is capped at the plan allowance. `credits_used_month` is TOTAL
 *     consumption, so charging all of it against the allowance made the bar read past its
 *     own limit ("3,226 / 2,000") and attributed top-up spend to the plan.
 *  2. Top-up usage is measured from the WALLET, not from usage counters. Usage counters
 *     move on every generation whether or not the top-up was touched; only `topup_credits`
 *     falling tells you a top-up was genuinely drawn on. Until it has been, the card shows
 *     a plain balance rather than a ratio against a ceiling nothing was spent from — which
 *     is the normal state in demo mode, where usage is inflated but nothing is deducted.
 */
class UsageCreditSplitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        config(['inertia.testing.ensure_pages_exist' => false]);
        settings_set('license_type', '2', 'integer', 'license');
    }

    private function plan(int $credits = 2000): Plan
    {
        return Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 10, 'price_yearly' => 100,
            'vat_percentage' => 0, 'credits' => $credits,
            'is_active' => true, 'is_free' => false, 'sort_order' => 2,
        ]);
    }

    /** @return array<string, mixed> */
    private function statsFor(User $user): array
    {
        $response = $this->actingAs($user)->get(route('user.dashboard.usage.index'));
        $response->assertOk();

        return $response->getOriginalContent()->getData()['page']['props']['stats'];
    }

    private function subscriber(int $planCredits, float $usedThisMonth, float $toppedUp = 0): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => now(),
            'plan_id' => $this->plan($planCredits)->id,
            'subscription_status' => 'active',
            'credits_used_month' => $usedThisMonth,
        ]);

        if ($toppedUp > 0) {
            // Through addCredits so topup_credits and the ledger are maintained for real.
            $user->addCredits($toppedUp, 'topup', 'Credit top-up purchase');
        }

        return $user->fresh();
    }

    // ─── Plan allowance ─────────────────────────

    /** The reported case: 3,226 consumed against a 2,000 allowance. */
    public function test_plan_usage_never_exceeds_the_allowance(): void
    {
        $stats = $this->statsFor($this->subscriber(planCredits: 2000, usedThisMonth: 3226, toppedUp: 3000));

        $this->assertSame(2000.0, (float) $stats['plan_credits_used_month']);
        $this->assertSame(2000.0, (float) $stats['plan_credit_limit']);
    }

    public function test_plan_usage_inside_the_allowance_is_reported_as_is(): void
    {
        $stats = $this->statsFor($this->subscriber(planCredits: 2000, usedThisMonth: 1500));

        $this->assertSame(1500.0, (float) $stats['plan_credits_used_month']);
    }

    // ─── Top-up ─────────────────────────────────

    /**
     * The demo-mode case, and the one that produced a bogus ratio: usage far exceeds the
     * allowance but the wallet was never actually charged, so the top-up is untouched.
     */
    public function test_exceeding_the_allowance_does_not_by_itself_report_top_up_usage(): void
    {
        $stats = $this->statsFor($this->subscriber(planCredits: 2000, usedThisMonth: 3226, toppedUp: 3000));

        $this->assertSame(0.0, (float) $stats['topup_credits_used']);
        // Zero total is the signal to show the plain balance instead of a ratio.
        $this->assertSame(0.0, (float) $stats['topup_credits_total']);
        $this->assertSame(3000.0, (float) $stats['topup_credits']);
    }

    /** Once the wallet drops into the top-up, the ratio becomes real. */
    public function test_credits_actually_drawn_from_the_top_up_are_reported(): void
    {
        $user = $this->subscriber(planCredits: 2000, usedThisMonth: 0, toppedUp: 5000);

        // 2,000 plan + 5,000 top-up, then spend 4,000: the allowance goes first, so 2,000
        // comes out of the top-up and deductCredits clamps topup_credits to 3,000.
        $user->forceFill(['credits' => 7000])->save();
        $user->deductCredits(4000, 'Generation');

        $stats = $this->statsFor($user->fresh());

        $this->assertSame(3000.0, (float) $user->fresh()->topup_credits);
        $this->assertSame(2000.0, (float) $stats['topup_credits_used']);
        $this->assertSame(5000.0, (float) $stats['topup_credits_total']);
    }

    /** A plan allowance grant is not a top-up and must not appear in either figure. */
    public function test_a_plan_allowance_grant_is_not_counted_as_a_top_up(): void
    {
        $user = $this->subscriber(planCredits: 2000, usedThisMonth: 0, toppedUp: 500);
        $user->addCredits(2000, 'purchase', 'Plan allowance');

        $stats = $this->statsFor($user->fresh());

        $this->assertSame(500.0, (float) $stats['topup_credits']);
        $this->assertSame(0.0, (float) $stats['topup_credits_used']);
    }

    public function test_a_user_who_never_topped_up_reports_nothing(): void
    {
        $stats = $this->statsFor($this->subscriber(planCredits: 2000, usedThisMonth: 3226));

        $this->assertSame(0.0, (float) $stats['topup_credits']);
        $this->assertSame(0.0, (float) $stats['topup_credits_used']);
        $this->assertSame(0.0, (float) $stats['topup_credits_total']);
    }

    /** Both keys must always be present — a missing one rendered as "NaN" in the badge. */
    public function test_both_top_up_keys_are_always_present(): void
    {
        $stats = $this->statsFor($this->subscriber(planCredits: 2000, usedThisMonth: 0));

        $this->assertArrayHasKey('topup_credits_used', $stats);
        $this->assertArrayHasKey('topup_credits_total', $stats);
        $this->assertIsFloat($stats['topup_credits_used']);
        $this->assertIsFloat($stats['topup_credits_total']);
    }
}
