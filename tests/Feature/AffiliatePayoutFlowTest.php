<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\AffiliateProgram;
use App\Models\User;
use App\Services\AffiliateService;
use Tests\TestCase;

/**
 * Payout lifecycle: a user requesting a payout (validation, balance, limits,
 * disabled) and an admin processing it (paid marks commissions, rejected returns
 * the funds, terminal states are final), plus the affiliate middleware gate.
 */
class AffiliatePayoutFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        settings_set('license_type', '2', 'integer', 'license');
        settings_set('affiliate_enabled', '1', 'boolean', 'affiliate');
        settings_set('default_currency', 'USD', 'string', 'general');
        config(['license.require_verified' => false]);

        $this->instance(
            \App\Services\NotificationEventService::class,
            \Mockery::mock(\App\Services\NotificationEventService::class)->shouldIgnoreMissing(),
        );
    }

    private function program(array $overrides = []): AffiliateProgram
    {
        return tap(AffiliateProgram::current())->update(array_merge([
            'payouts_enabled' => true,
            'min_payout' => 20,
            'max_payout' => 0,
            'commission_hold_days' => 0,
            'payout_methods' => ['paypal', 'bank_transfer', 'credits'],
        ], $overrides));
    }

    private function user(): User
    {
        return User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    }

    /** Give a user a withdrawable balance via an approved, past-hold commission. */
    private function fundBalance(User $user, float $amount): void
    {
        AffiliateCommission::create([
            'referrer_id' => $user->id,
            'referred_id' => User::factory()->create()->id,
            'amount' => $amount,
            'status' => 'approved',
            'approved_at' => now()->subDay(),
        ]);
    }

    private function superAdmin(): Admin
    {
        $slug = config('auth.providers.admins.super_admin_slug', 'super-admin');
        $role = AdminRole::firstOrCreate(['slug' => $slug], ['name' => 'Super Admin', 'is_system' => true]);

        return Admin::create([
            'name' => 'Super Admin',
            'email' => 'affiliate-flow-'.uniqid().'@makeai.test',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    // ─── user requests a payout ──────────────────

    public function test_a_user_can_request_a_payout_within_their_balance(): void
    {
        $this->program();
        $user = $this->user();
        $this->fundBalance($user, 50);

        $this->actingAs($user)
            ->post(route('user.dashboard.affiliate.payouts.store'), [
                'amount' => 30,
                'method' => 'paypal',
                'details' => ['paypal_email' => 'aff@example.test'],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('affiliate_payouts', [
            'user_id' => $user->id, 'amount' => 30, 'method' => 'paypal', 'status' => 'pending',
        ]);
    }

    public function test_a_payout_below_the_minimum_is_rejected(): void
    {
        $this->program(['min_payout' => 20]);
        $user = $this->user();
        $this->fundBalance($user, 50);

        $this->actingAs($user)
            ->post(route('user.dashboard.affiliate.payouts.store'), [
                'amount' => 10, 'method' => 'paypal', 'details' => ['paypal_email' => 'aff@example.test'],
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, AffiliatePayout::count());
    }

    public function test_a_payout_above_the_maximum_is_rejected(): void
    {
        $this->program(['max_payout' => 40]);
        $user = $this->user();
        $this->fundBalance($user, 100);

        $this->actingAs($user)
            ->post(route('user.dashboard.affiliate.payouts.store'), [
                'amount' => 45, 'method' => 'paypal', 'details' => ['paypal_email' => 'aff@example.test'],
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, AffiliatePayout::count());
    }

    public function test_a_payout_exceeding_the_available_balance_is_rejected(): void
    {
        $this->program();
        $user = $this->user();
        $this->fundBalance($user, 50);

        $this->actingAs($user)
            ->post(route('user.dashboard.affiliate.payouts.store'), [
                'amount' => 60, 'method' => 'paypal', 'details' => ['paypal_email' => 'aff@example.test'],
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, AffiliatePayout::count());
    }

    public function test_paypal_method_requires_a_paypal_email(): void
    {
        $this->program();
        $user = $this->user();
        $this->fundBalance($user, 50);

        $this->actingAs($user)
            ->post(route('user.dashboard.affiliate.payouts.store'), [
                'amount' => 30, 'method' => 'paypal', 'details' => ['note' => 'no email'],
            ])
            ->assertSessionHasErrors('details.paypal_email');
    }

    public function test_a_payout_request_is_forbidden_when_payouts_are_disabled(): void
    {
        $this->program(['payouts_enabled' => false]);
        $user = $this->user();
        $this->fundBalance($user, 50);

        $this->actingAs($user)
            ->post(route('user.dashboard.affiliate.payouts.store'), [
                'amount' => 30, 'method' => 'paypal', 'details' => ['paypal_email' => 'aff@example.test'],
            ])
            ->assertForbidden();
    }

    // ─── admin processes a payout ────────────────

    public function test_admin_marking_a_payout_paid_settles_matching_commissions(): void
    {
        $this->program();
        $user = $this->user();
        $commission = AffiliateCommission::create([
            'referrer_id' => $user->id,
            'referred_id' => User::factory()->create()->id,
            'amount' => 30, 'status' => 'approved', 'approved_at' => now()->subDay(),
        ]);
        $payout = AffiliatePayout::create(['user_id' => $user->id, 'amount' => 30, 'method' => 'paypal', 'status' => 'pending']);

        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.affiliate.payouts.process', $payout), ['status' => 'paid'])
            ->assertSessionHasNoErrors();

        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame('paid', $commission->fresh()->status);
    }

    public function test_rejecting_a_payout_returns_the_funds_to_the_balance(): void
    {
        $this->program();
        $user = $this->user();
        $this->fundBalance($user, 50);
        $payout = AffiliatePayout::create(['user_id' => $user->id, 'amount' => 20, 'method' => 'paypal', 'status' => 'pending']);

        // In flight, the pending payout reduces the balance to 30.
        $this->assertEqualsWithDelta(30.0, app(AffiliateService::class)->availableBalance($user), 0.001);

        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.affiliate.payouts.process', $payout), ['status' => 'rejected'])
            ->assertSessionHasNoErrors();

        $this->assertSame('rejected', $payout->fresh()->status);
        // Funds returned — the full 50 is withdrawable again.
        $this->assertEqualsWithDelta(50.0, app(AffiliateService::class)->availableBalance($user), 0.001);
    }

    public function test_a_finalized_payout_cannot_be_reprocessed(): void
    {
        $this->program();
        $user = $this->user();
        $payout = AffiliatePayout::create(['user_id' => $user->id, 'amount' => 20, 'method' => 'paypal', 'status' => 'paid', 'processed_at' => now()]);

        $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.affiliate.payouts.process', $payout), ['status' => 'rejected'])
            ->assertSessionHasErrors('status');

        $this->assertSame('paid', $payout->fresh()->status);
    }

    // ─── middleware gate ─────────────────────────

    public function test_the_affiliate_dashboard_is_hidden_when_the_program_is_disabled(): void
    {
        settings_set('affiliate_enabled', '0', 'boolean', 'affiliate');

        $this->actingAs($this->user())
            ->get(route('user.dashboard.affiliate'))
            ->assertNotFound();
    }
}
