<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\AffiliateProgram;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\Subscription\SubscriptionLifecycleService;
use Tests\TestCase;

/**
 * Affiliate audit fixes:
 *  - commissions normalized to the store base currency (foreign per-country orders)
 *  - first-purchase referral credits clawed back when the order is refunded
 *  - one commission per order (idempotent)
 */
class AffiliateAuditFixesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        settings_set('license_type', '2', 'integer', 'license');       // Extended
        settings_set('subscriptions_enabled', '1', 'boolean', 'ai');
        settings_set('affiliate_enabled', '1', 'boolean', 'affiliate');
        settings_set('default_currency', 'USD', 'string', 'general');

        // Notification events broadcast to Reverb/Pusher — mute them in tests.
        $this->instance(
            \App\Services\NotificationEventService::class,
            \Mockery::mock(\App\Services\NotificationEventService::class)->shouldIgnoreMissing(),
        );
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

    private function pair(): array
    {
        $referrer = User::factory()->create();
        $referred = User::factory()->create(['referred_by' => $referrer->id]);

        return [$referrer, $referred];
    }

    public function test_commission_is_normalized_to_base_currency(): void
    {
        $this->program();
        [$referrer, $referred] = $this->pair();

        // INR order (₹4000) with 1 USD = 80 INR → $50 base → 20% = $10.
        Currency::create(['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'exchange_rate' => 1, 'is_active' => true]);
        Currency::create(['code' => 'INR', 'symbol' => '₹', 'name' => 'Rupee', 'exchange_rate' => 80, 'is_active' => true]);

        $payment = Payment::create([
            'user_id' => $referred->id, 'gateway' => 'stripe', 'amount' => 4000,
            'currency' => 'INR', 'status' => 'completed', 'type' => 'subscription',
        ]);

        app(AffiliateService::class)->createCommissionForPayment($payment);

        $commission = AffiliateCommission::where('order_id', $payment->id)->first();
        $this->assertNotNull($commission);
        $this->assertEqualsWithDelta(10.0, (float) $commission->amount, 0.001, 'Commission must be 20% of the base-currency value, not 20% of ₹4000.');
    }

    public function test_only_one_commission_per_order(): void
    {
        $this->program();
        [$referrer, $referred] = $this->pair();

        $payment = Payment::create([
            'user_id' => $referred->id, 'gateway' => 'stripe', 'amount' => 100,
            'currency' => 'USD', 'status' => 'completed', 'type' => 'subscription',
        ]);

        $service = app(AffiliateService::class);
        $first = $service->createCommissionForPayment($payment);
        $second = $service->createCommissionForPayment($payment);

        $this->assertNotNull($first);
        $this->assertNull($second, 'A second attempt for the same order must be a no-op.');
        $this->assertSame(1, AffiliateCommission::where('order_id', $payment->id)->count());
    }

    private function aliasRules(User $user): array
    {
        $request = \App\Http\Requests\AffiliateAliasRequest::create('/', 'POST', []);
        $request->setUserResolver(fn () => $user);

        return $request->rules();
    }

    public function test_custom_alias_cannot_hijack_another_users_referral_code(): void
    {
        // All-numeric code so the collision is exact-case (cross-DB safe). findReferrer()
        // matches referral_code OR affiliate_custom_slug, so an alias equal to another
        // user's code would hijack their referral link.
        User::factory()->create(['referral_code' => '12345678']);
        $attacker = User::factory()->create();

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['custom_slug' => '12345678'],
            $this->aliasRules($attacker),
        );

        $this->assertTrue($validator->fails(), 'Alias equal to another user\'s referral code must be rejected.');
        $this->assertArrayHasKey('custom_slug', $validator->errors()->toArray());
    }

    public function test_custom_alias_rejects_reserved_words(): void
    {
        $user = User::factory()->create();

        $validator = \Illuminate\Support\Facades\Validator::make(
            ['custom_slug' => 'admin'],
            $this->aliasRules($user),
        );

        $this->assertTrue($validator->fails(), 'Reserved slugs must be rejected.');
        $this->assertArrayHasKey('custom_slug', $validator->errors()->toArray());
    }

    public function test_referral_totals_derive_from_ledger_and_survive_rejections(): void
    {
        $this->program(['auto_approve_commissions' => true, 'commission_hold_days' => 0, 'commission_value' => 20]);
        [$referrer, $referred] = $this->pair();
        $service = app(AffiliateService::class);

        // Two $100 orders → two auto-approved $20 commissions.
        foreach ([100, 100] as $i => $amount) {
            $p = Payment::create([
                'user_id' => $referred->id, 'gateway' => 'stripe', 'amount' => $amount,
                'currency' => 'USD', 'status' => 'completed', 'type' => 'subscription',
            ]);
            $service->createCommissionForPayment($p);
        }

        $referrer->refresh();
        $this->assertEqualsWithDelta(40.0, (float) $referrer->referral_earnings, 0.001);
        $this->assertSame(2, (int) $referrer->referral_count);

        // Reject one → totals recompute from the ledger (earnings 20, count 1).
        $first = AffiliateCommission::where('referrer_id', $referrer->id)->oldest()->first();
        $service->rejectCommission($first);

        $referrer->refresh();
        $this->assertEqualsWithDelta(20.0, (float) $referrer->referral_earnings, 0.001);
        $this->assertSame(1, (int) $referrer->referral_count);

        // Cached counter always equals the ledger sum → no drift vs availableBalance.
        $ledger = (float) AffiliateCommission::where('referrer_id', $referrer->id)
            ->whereIn('status', ['approved', 'paid'])->sum('amount');
        $this->assertEqualsWithDelta($ledger, (float) $referrer->referral_earnings, 0.001);
        $this->assertEqualsWithDelta($ledger, $service->availableBalance($referrer), 0.001, 'Lifetime cache and withdrawable both derive from one ledger.');
    }

    public function test_rejecting_a_pending_commission_does_not_inflate_count(): void
    {
        // The old inc/decrement bug: a pending commission bumped referral_count but a
        // pending rejection never decremented it. The ledger recompute self-heals this.
        $this->program(['auto_approve_commissions' => false]);
        [$referrer, $referred] = $this->pair();
        $service = app(AffiliateService::class);

        $p = Payment::create([
            'user_id' => $referred->id, 'gateway' => 'stripe', 'amount' => 100,
            'currency' => 'USD', 'status' => 'completed', 'type' => 'subscription',
        ]);
        $commission = $service->createCommissionForPayment($p); // pending

        $this->assertSame(0, (int) $referrer->fresh()->referral_count, 'A pending commission is not counted as earned.');

        $service->rejectCommission($commission);
        $this->assertSame(0, (int) $referrer->fresh()->referral_count, 'Rejecting a pending commission leaves the count at 0 (no drift).');
    }

    private function superAdmin(): Admin
    {
        $superSlug = config('auth.providers.admins.super_admin_slug', 'super-admin');
        $role = AdminRole::firstOrCreate(
            ['slug' => $superSlug],
            ['name' => 'Super Admin', 'is_system' => true],
        );

        return Admin::create([
            'name' => 'Super Admin',
            'email' => 'affiliate-payout-test@makeai.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    public function test_credits_payout_converts_currency_via_credit_price_anchor(): void
    {
        // A $10 affiliate balance paid out as wallet credits must grant credits
        // through the store price anchor (credit_price_per_unit), NOT the raw
        // currency amount. At $0.01/credit, $10 = 1000 credits — the old bug
        // granted 10 credits (worth $0.10), shorting the affiliate 100×.
        config(['license.require_verified' => false]);
        settings_set('credit_price_per_unit', 0.01, 'string', 'billing');
        $this->program(['payouts_enabled' => true, 'commission_hold_days' => 0]);

        [$referrer, $referred] = $this->pair();
        $referrer->update(['credits' => 0]);
        AffiliateCommission::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $referred->id,
            'amount' => 10,
            'status' => 'approved',
            'approved_at' => now()->subDay(),
        ]);

        $payout = AffiliatePayout::create([
            'user_id' => $referrer->id,
            'amount' => 10,
            'method' => 'credits',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.affiliate.payouts.process', $payout), ['status' => 'paid']);

        $response->assertSessionHasNoErrors();
        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertEqualsWithDelta(1000.0, (float) $referrer->fresh()->credits, 0.001, '$10 at $0.01/credit must grant 1000 credits.');
    }

    public function test_credits_payout_is_blocked_when_credit_price_is_zero(): void
    {
        // A misconfigured (zero) credit price would divide-to-nothing; the payout
        // must be refused rather than marked paid while granting 0 credits.
        config(['license.require_verified' => false]);
        settings_set('credit_price_per_unit', 0, 'string', 'billing');
        $this->program(['payouts_enabled' => true, 'commission_hold_days' => 0]);

        [$referrer, $referred] = $this->pair();
        $referrer->update(['credits' => 0]);
        AffiliateCommission::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $referred->id,
            'amount' => 10,
            'status' => 'approved',
            'approved_at' => now()->subDay(),
        ]);

        $payout = AffiliatePayout::create([
            'user_id' => $referrer->id,
            'amount' => 10,
            'method' => 'credits',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->superAdmin(), 'admin')
            ->post(route('admin.affiliate.payouts.process', $payout), ['status' => 'paid']);

        $response->assertSessionHasErrors('status');
        $this->assertSame('pending', $payout->fresh()->status, 'Payout must stay pending when the credit price is misconfigured.');
        $this->assertEqualsWithDelta(0.0, (float) $referrer->fresh()->credits, 0.001);
    }

    public function test_referral_credits_are_clawed_back_on_refund(): void
    {
        $this->program([
            'referral_credits_enabled' => true,
            'referral_credits_amount' => 500,
        ]);
        [$referrer, $referred] = $this->pair();
        $startCredits = (float) $referrer->fresh()->credits;

        $payment = Payment::create([
            'user_id' => $referred->id, 'gateway' => 'stripe', 'amount' => 100,
            'currency' => 'USD', 'status' => 'completed', 'type' => 'subscription',
        ]);

        $service = app(AffiliateService::class);
        $service->awardReferralCreditsForPayment($payment);
        $this->assertEqualsWithDelta($startCredits + 500, (float) $referrer->fresh()->credits, 0.001);

        // Refund → credits reversed.
        app(SubscriptionLifecycleService::class)->refund($payment);

        $this->assertEqualsWithDelta($startCredits, (float) $referrer->fresh()->credits, 0.001, 'Referral credits must be reversed on refund.');

        // Idempotent — a second clawback does nothing further.
        $service->clawbackCommissionForPayment($payment->fresh());
        $this->assertEqualsWithDelta($startCredits, (float) $referrer->fresh()->credits, 0.001);
    }
}
