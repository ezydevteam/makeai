<?php

namespace Tests\Feature;

use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\AffiliateProgram;
use App\Models\Payment;
use App\Models\User;
use App\Services\AffiliateService;
use Tests\TestCase;

/**
 * The commission engine — createCommissionForPayment across every program mode,
 * the approve/reject accounting, availableBalance (hold period + payouts), and
 * markCommissionsPaid matching. Service level, no HTTP.
 */
class AffiliateCommissionEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        settings_set('license_type', '2', 'integer', 'license');       // Extended
        settings_set('affiliate_enabled', '1', 'boolean', 'affiliate');
        settings_set('default_currency', 'USD', 'string', 'general');

        $this->instance(
            \App\Services\NotificationEventService::class,
            \Mockery::mock(\App\Services\NotificationEventService::class)->shouldIgnoreMissing(),
        );
    }

    private function service(): AffiliateService
    {
        return app(AffiliateService::class);
    }

    private function program(array $overrides = []): AffiliateProgram
    {
        return tap(AffiliateProgram::current())->update(array_merge([
            'commission_type' => 'percentage',
            'commission_value' => 20,
            'commission_on' => 'all_purchases',
            'auto_approve_commissions' => true,
            'commission_hold_days' => 0,
        ], $overrides));
    }

    /** @return array{0:User,1:User} [referrer, referred] */
    private function pair(): array
    {
        $referrer = User::factory()->create();

        return [$referrer, User::factory()->create(['referred_by' => $referrer->id])];
    }

    private function payment(User $referred, array $attrs = []): Payment
    {
        return Payment::create(array_merge([
            'user_id' => $referred->id, 'gateway' => 'stripe', 'amount' => 100,
            'currency' => 'USD', 'status' => 'completed', 'type' => 'subscription',
        ], $attrs));
    }

    // ─── commission amount types ─────────────────

    public function test_percentage_commission_is_a_percentage_of_the_order(): void
    {
        $this->program(['commission_type' => 'percentage', 'commission_value' => 15]);
        [, $referred] = $this->pair();

        $commission = $this->service()->createCommissionForPayment($this->payment($referred, ['amount' => 200]));

        $this->assertEqualsWithDelta(30.0, (float) $commission->amount, 0.001);
    }

    public function test_fixed_commission_ignores_the_order_amount(): void
    {
        $this->program(['commission_type' => 'fixed', 'commission_value' => 7]);
        [, $referred] = $this->pair();

        $commission = $this->service()->createCommissionForPayment($this->payment($referred, ['amount' => 999]));

        $this->assertEqualsWithDelta(7.0, (float) $commission->amount, 0.001);
    }

    public function test_a_zero_commission_is_not_recorded(): void
    {
        $this->program(['commission_type' => 'fixed', 'commission_value' => 0]);
        [, $referred] = $this->pair();

        $this->assertNull($this->service()->createCommissionForPayment($this->payment($referred)));
        $this->assertSame(0, AffiliateCommission::count());
    }

    // ─── commission_on modes ─────────────────────

    public function test_subscription_mode_skips_a_credit_topup_order(): void
    {
        $this->program(['commission_on' => 'subscription']);
        [, $referred] = $this->pair();

        $this->assertNull($this->service()->createCommissionForPayment($this->payment($referred, ['type' => 'credit_topup'])));
        $this->assertNotNull($this->service()->createCommissionForPayment($this->payment($referred, ['type' => 'subscription'])));
    }

    public function test_all_purchases_mode_rewards_a_credit_topup_order(): void
    {
        $this->program(['commission_on' => 'all_purchases']);
        [, $referred] = $this->pair();

        $this->assertNotNull($this->service()->createCommissionForPayment($this->payment($referred, ['type' => 'credit_topup'])));
    }

    public function test_first_purchase_mode_only_rewards_the_users_first_order(): void
    {
        $this->program(['commission_on' => 'first_purchase']);
        [, $referred] = $this->pair();

        // An earlier completed order exists → the second order earns nothing.
        $this->payment($referred, ['amount' => 40]);
        $second = $this->payment($referred, ['amount' => 100]);

        $this->assertNull($this->service()->createCommissionForPayment($second));
    }

    public function test_first_purchase_mode_rewards_a_users_only_order(): void
    {
        $this->program(['commission_on' => 'first_purchase']);
        [, $referred] = $this->pair();

        $this->assertNotNull($this->service()->createCommissionForPayment($this->payment($referred)));
    }

    // ─── guards ──────────────────────────────────

    public function test_a_user_with_no_referrer_earns_no_commission(): void
    {
        $this->program();
        $orphan = User::factory()->create(['referred_by' => null]);

        $this->assertNull($this->service()->createCommissionForPayment($this->payment($orphan)));
    }

    public function test_a_banned_referrer_earns_no_commission(): void
    {
        $this->program();
        $referrer = User::factory()->create(['affiliate_banned' => true]);
        $referred = User::factory()->create(['referred_by' => $referrer->id]);

        $this->assertNull($this->service()->createCommissionForPayment($this->payment($referred)));
    }

    public function test_the_commission_system_is_inert_when_disabled(): void
    {
        settings_set('affiliate_enabled', '0', 'boolean', 'affiliate');
        $this->program();
        [, $referred] = $this->pair();

        $this->assertNull($this->service()->createCommissionForPayment($this->payment($referred)));
    }

    // ─── approval accounting ─────────────────────

    public function test_auto_approve_credits_earnings_immediately_pending_does_not(): void
    {
        // pending
        $this->program(['auto_approve_commissions' => false]);
        [$referrer, $referred] = $this->pair();
        $commission = $this->service()->createCommissionForPayment($this->payment($referred));

        $this->assertSame('pending', $commission->status);
        $this->assertEqualsWithDelta(0.0, (float) $referrer->fresh()->referral_earnings, 0.001);

        // manual approval credits the ledger
        $this->service()->approveCommission($commission);
        $this->assertSame('approved', $commission->fresh()->status);
        $this->assertEqualsWithDelta(20.0, (float) $referrer->fresh()->referral_earnings, 0.001);
    }

    public function test_approving_the_same_commission_twice_does_not_double_earnings(): void
    {
        $this->program(['auto_approve_commissions' => false]);
        [$referrer, $referred] = $this->pair();
        $commission = $this->service()->createCommissionForPayment($this->payment($referred));

        $this->service()->approveCommission($commission);
        $this->service()->approveCommission($commission->fresh());

        $this->assertEqualsWithDelta(20.0, (float) $referrer->fresh()->referral_earnings, 0.001);
        $this->assertSame(1, (int) $referrer->fresh()->referral_count);
    }

    // ─── availableBalance ────────────────────────

    public function test_earnings_are_withheld_until_the_hold_period_passes(): void
    {
        $this->program(['commission_hold_days' => 14]);
        [$referrer, $referred] = $this->pair();

        // Approved just now → still within the 14-day hold → nothing withdrawable.
        AffiliateCommission::create([
            'referrer_id' => $referrer->id, 'referred_id' => $referred->id,
            'amount' => 50, 'status' => 'approved', 'approved_at' => now(),
        ]);
        $this->assertEqualsWithDelta(0.0, $this->service()->availableBalance($referrer), 0.001);

        // Approved 20 days ago → past the hold → withdrawable.
        AffiliateCommission::create([
            'referrer_id' => $referrer->id, 'referred_id' => $referred->id,
            'amount' => 30, 'status' => 'approved', 'approved_at' => now()->subDays(20),
        ]);
        $this->assertEqualsWithDelta(30.0, $this->service()->availableBalance($referrer), 0.001);
    }

    public function test_pending_and_paid_payouts_reduce_the_balance_but_rejected_ones_do_not(): void
    {
        $this->program(['commission_hold_days' => 0]);
        [$referrer, $referred] = $this->pair();
        AffiliateCommission::create([
            'referrer_id' => $referrer->id, 'referred_id' => $referred->id,
            'amount' => 50, 'status' => 'approved', 'approved_at' => now()->subDay(),
        ]);

        // A pending payout in flight reduces the balance.
        AffiliatePayout::create(['user_id' => $referrer->id, 'amount' => 20, 'method' => 'paypal', 'status' => 'pending']);
        $this->assertEqualsWithDelta(30.0, $this->service()->availableBalance($referrer), 0.001);

        // A rejected payout returns the funds — it must NOT reduce the balance.
        AffiliatePayout::create(['user_id' => $referrer->id, 'amount' => 100, 'method' => 'paypal', 'status' => 'rejected']);
        $this->assertEqualsWithDelta(30.0, $this->service()->availableBalance($referrer), 0.001);
    }

    // ─── markCommissionsPaid ─────────────────────

    public function test_mark_commissions_paid_takes_oldest_first_and_skips_ones_too_large(): void
    {
        $this->program(['commission_hold_days' => 0]);
        [$referrer, $referred] = $this->pair();

        $mk = function (float $amount, int $daysAgo) use ($referrer, $referred) {
            return AffiliateCommission::create([
                'referrer_id' => $referrer->id, 'referred_id' => $referred->id,
                'amount' => $amount, 'status' => 'approved', 'approved_at' => now()->subDays($daysAgo),
            ]);
        };
        $c10 = $mk(10, 3);   // oldest
        $c30 = $mk(30, 2);
        $c5 = $mk(5, 1);     // newest

        // Payout of 20: takes the $10 (remaining 10), skips the $30 (too large),
        // takes the $5 (remaining 5). $15 of commissions move to paid.
        $paid = $this->service()->markCommissionsPaid($referrer->id, 20, 0);

        $this->assertCount(2, $paid);
        $this->assertSame('paid', $c10->fresh()->status);
        $this->assertSame('approved', $c30->fresh()->status);
        $this->assertSame('paid', $c5->fresh()->status);
    }
}
