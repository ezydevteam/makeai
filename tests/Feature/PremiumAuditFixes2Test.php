<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\GatewaySubscription;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\Subscription\SubscriptionLifecycleService;
use App\Services\Subscription\SubscriptionProrationService;
use Tests\TestCase;

/**
 * Second batch of premium-audit fixes:
 *  #4  coupon global over-redemption (reserve at checkout / release on failure)
 *      + recurring-coupon revalidation on renewal
 *  medium: proration on non-monthly cycles, grace-period revocation
 */
class PremiumAuditFixes2Test extends TestCase
{
    private function coupon(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'C'.strtoupper(uniqid()),
            'type' => 'percent',
            'value' => 50,
            'max_uses' => 1,
            'used_count' => 0,
            'is_recurring' => false,
            'is_active' => true,
        ], $overrides));
    }

    // ─── #4 global reservation ───────────────────────────────────

    public function test_reserve_global_use_enforces_max_uses(): void
    {
        $coupon = $this->coupon(['max_uses' => 1]);

        $this->assertTrue($coupon->reserveGlobalUse(), 'First reservation claims the only slot.');
        $this->assertFalse($coupon->fresh()->reserveGlobalUse(), 'Second reservation is rejected — no over-redemption.');
        $this->assertSame(1, (int) $coupon->fresh()->used_count);
    }

    public function test_release_global_use_returns_slot_and_floors_at_zero(): void
    {
        $coupon = $this->coupon(['max_uses' => 2, 'used_count' => 1]);

        $coupon->releaseGlobalUse();
        $this->assertSame(0, (int) $coupon->fresh()->used_count);

        // Never goes negative.
        $coupon->fresh()->releaseGlobalUse();
        $this->assertSame(0, (int) $coupon->fresh()->used_count);
    }

    public function test_unlimited_coupon_reservation_always_succeeds(): void
    {
        $coupon = $this->coupon(['max_uses' => null]);

        $this->assertTrue($coupon->reserveGlobalUse());
        $this->assertTrue($coupon->fresh()->reserveGlobalUse());
    }

    // ─── #4 recurring-coupon revalidation on renewal ─────────────

    public function test_expired_recurring_coupon_not_reapplied_on_renewal(): void
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 100, 'price_yearly' => 1000, 'vat_percentage' => 0,
            'credits' => 1000, 'is_active' => true, 'is_free' => false, 'sort_order' => 1,
        ]);
        $user = User::factory()->create();

        // An EXPIRED recurring coupon that was used on the original payment.
        $coupon = $this->coupon(['is_recurring' => true, 'is_active' => true, 'expires_at' => now()->subDay(), 'max_uses' => null]);

        $subscription = GatewaySubscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'status' => 'active', 'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_'.uniqid(), 'amount' => 50, 'currency' => 'USD',
            'current_period_start' => now()->subMonth(), 'current_period_end' => now(),
        ]);
        Payment::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'subscription_id' => $subscription->id,
            'gateway' => 'stripe', 'gateway_payment_id' => 'orig_'.uniqid(), 'amount' => 50,
            'currency' => 'USD', 'status' => 'completed', 'type' => 'subscription',
            'metadata' => ['coupon_code' => $coupon->code],
        ]);

        app(SubscriptionLifecycleService::class)->renewFromGatewaySubscription(
            'stripe', $subscription->gateway_subscription_id, 'renew_'.uniqid(), 100.0, 'USD',
        );

        // Expired coupon must NOT discount the renewal — charged the full $100.
        $renewal = Payment::where('subscription_id', $subscription->id)
            ->where('gateway_payment_id', 'like', 'renew_%')->first();
        $this->assertNotNull($renewal);
        $this->assertEquals(100.0, (float) $renewal->amount, 'Expired recurring coupon must not discount renewal.');
    }

    // ─── abandoned-checkout cleanup (releases coupon slots) ──────

    public function test_expire_abandoned_releases_coupon_and_spares_bank_and_recent(): void
    {
        $user = User::factory()->create();
        $coupon = $this->coupon(['max_uses' => 5, 'used_count' => 1]); // 1 slot reserved

        // Stale abandoned Stripe checkout holding the coupon slot.
        $stale = Payment::create([
            'user_id' => $user->id, 'gateway' => 'stripe', 'amount' => 10, 'currency' => 'USD',
            'status' => 'pending', 'type' => 'subscription',
            'metadata' => ['coupon_code' => $coupon->code, 'coupon_global_reserved' => true],
        ]);
        $stale->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();
        // A bank transfer awaiting admin review — must NOT be expired.
        $bank = Payment::create([
            'user_id' => $user->id, 'gateway' => 'bank_transfer', 'amount' => 10, 'currency' => 'USD',
            'status' => 'pending', 'type' => 'subscription',
        ]);
        $bank->forceFill(['created_at' => now()->subDays(5)])->saveQuietly();
        // A recent pending checkout — still in flight, must NOT be expired.
        $recent = Payment::create([
            'user_id' => $user->id, 'gateway' => 'stripe', 'amount' => 10, 'currency' => 'USD',
            'status' => 'pending', 'type' => 'subscription',
        ]);

        $count = app(SubscriptionLifecycleService::class)->expireAbandonedCheckouts(24);

        $this->assertSame(1, $count);
        $this->assertSame('failed', $stale->fresh()->status);
        $this->assertSame(0, (int) $coupon->fresh()->used_count, 'Abandoned checkout released the coupon slot.');
        $this->assertSame('pending', $bank->fresh()->status, 'Bank transfer preserved.');
        $this->assertSame('pending', $recent->fresh()->status, 'Recent checkout preserved.');
    }

    // ─── medium: proration on non-monthly cycles ─────────────────

    public function test_proration_uses_time_ratio_for_yearly_cycle(): void
    {
        $plan = Plan::create([
            'name' => 'Annual', 'slug' => 'annual-'.uniqid(),
            'price_monthly' => 0, 'price_yearly' => 120, 'vat_percentage' => 0,
            'credits' => 12000, 'is_active' => true, 'is_free' => false, 'sort_order' => 1,
        ]);
        // Heavy annual user, but credits_used_month was just reset (calendar month).
        $user = User::factory()->create(['credits_used_month' => 0]);

        $sub = GatewaySubscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'billing_cycle' => 'yearly',
            'status' => 'active', 'gateway' => 'stripe', 'amount' => 120, 'currency' => 'USD',
            'current_period_start' => now()->subMonths(11), // ~1 month left of the year
            'current_period_end' => now()->addMonth(),
        ]);

        $ratio = app(SubscriptionProrationService::class)->unusedCreditRatio($user, $plan, $sub);

        // Time-based: ~1/12 remaining — NOT ~1.0 that the reset credits_used_month would give.
        $this->assertLessThan(0.2, $ratio, 'Yearly proration should reflect elapsed time, not the monthly counter.');
    }

    // ─── medium: grace-period revocation ─────────────────────────

    public function test_downgrade_preserves_access_during_grace_period(): void
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 20, 'price_yearly' => 200, 'vat_percentage' => 0,
            'credits' => 1000, 'is_active' => true, 'is_free' => false, 'sort_order' => 1,
        ]);
        $user = User::factory()->create(['plan_id' => $plan->id, 'subscription_status' => 'active']);

        // A cancelled-but-in-grace subscription (paid through next week).
        $grace = GatewaySubscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'status' => 'cancelled', 'cancelled_at' => now(), 'gateway' => 'stripe',
            'amount' => 20, 'currency' => 'USD',
            'current_period_start' => now()->subDays(20), 'current_period_end' => now()->addWeek(),
        ]);

        // An unrelated OLD subscription now expiring should NOT revoke access.
        $old = GatewaySubscription::create([
            'user_id' => $user->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'status' => 'expired', 'gateway' => 'paypal', 'amount' => 20, 'currency' => 'USD',
            'current_period_start' => now()->subMonths(2), 'current_period_end' => now()->subMonth(),
        ]);

        app(SubscriptionLifecycleService::class)->expireNow($old);

        $fresh = $user->fresh();
        $this->assertSame($plan->id, $fresh->plan_id, 'Access retained via the in-grace subscription.');
        $this->assertNotSame('none', $fresh->subscription_status);
    }
}
