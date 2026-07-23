<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckExtendedLicense;
use App\Http\Middleware\CheckPremium;
use App\Http\Middleware\LicenseMiddleware;
use App\Models\Currency;
use App\Models\GatewaySubscription;
use App\Models\Plan;
use App\Models\User;
use Tests\TestCase;

/**
 * User-facing subscription management: /subscription/cancel, resume, downgrade,
 * upgrade, cancel-scheduled.
 *
 * These exercise the LOCAL (one-time-gateway) branch — a subscription with no
 * gateway_subscription_id — so no Stripe/PayPal HTTP is involved. The in-place
 * recurring branches (Stripe swap, PayPal revise) are covered at the service
 * level elsewhere; here we prove the controller wiring, guards and messaging.
 */
class SubscriptionLifecycleActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([CheckPremium::class, CheckExtendedLicense::class, LicenseMiddleware::class]);
        config(['broadcasting.default' => 'null']);

        Currency::create(['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'exchange_rate' => 1, 'is_default' => true, 'is_active' => true]);
        settings_set('default_currency', 'USD', 'string', 'general');
    }

    private function user(): User
    {
        return User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    }

    private function plan(string $name, int $sort, float $monthly = 10, bool $free = false): Plan
    {
        return Plan::create([
            'name' => $name,
            'slug' => strtolower($name).'-'.uniqid(),
            'price_monthly' => $monthly,
            'price_yearly' => $monthly * 10,
            'vat_percentage' => 0,
            'credits' => 1000,
            'is_active' => true,
            'is_free' => $free,
            'sort_order' => $sort,
        ]);
    }

    /** A live subscription bought through a one-time gateway (no remote sub id). */
    private function localSubscription(User $user, Plan $plan): GatewaySubscription
    {
        $sub = GatewaySubscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'status' => GatewaySubscription::STATUS_ACTIVE,
            'gateway' => 'bank_transfer',
            'gateway_subscription_id' => null,
            'amount' => $plan->price_monthly,
            'currency' => 'USD',
            'current_period_start' => now()->subDays(5),
            'current_period_end' => now()->addDays(25),
        ]);

        $user->update(['plan_id' => $plan->id, 'subscription_status' => 'active', 'subscription_ends_at' => now()->addDays(25)]);

        return $sub;
    }

    // ─── cancel ──────────────────────────────────

    public function test_cancel_marks_the_subscription_cancelled_but_keeps_access_until_period_end(): void
    {
        $user = $this->user();
        $plan = $this->plan('Pro', 2);
        $sub = $this->localSubscription($user, $plan);

        $this->actingAs($user)
            ->post('/subscription/cancel')
            ->assertSessionHas('success');

        $sub->refresh();
        $this->assertSame(GatewaySubscription::STATUS_CANCELLED, $sub->status);
        $this->assertNotNull($sub->cancelled_at);
        // Access is retained until the period end (grace period).
        $this->assertTrue($sub->current_period_end->isFuture());
        $this->assertSame('cancelled', $user->fresh()->subscription_status);
    }

    public function test_cancel_without_an_active_subscription_errors(): void
    {
        $this->actingAs($this->user())
            ->post('/subscription/cancel')
            ->assertSessionHas('error');
    }

    // ─── resume ──────────────────────────────────

    public function test_resume_reactivates_a_cancelled_subscription_within_grace(): void
    {
        $user = $this->user();
        $plan = $this->plan('Pro', 2);
        $sub = $this->localSubscription($user, $plan);

        // Cancel first, then resume — the real flow.
        $this->actingAs($user)->post('/subscription/cancel')->assertSessionHas('success');

        $this->actingAs($user)
            ->post('/subscription/resume')
            ->assertSessionHas('success');

        $sub->refresh();
        $this->assertSame(GatewaySubscription::STATUS_ACTIVE, $sub->status);
        $this->assertNull($sub->cancelled_at);
        $this->assertSame('active', $user->fresh()->subscription_status);
    }

    public function test_resume_with_nothing_to_resume_errors(): void
    {
        $this->actingAs($this->user())
            ->post('/subscription/resume')
            ->assertSessionHas('error');
    }

    public function test_a_cancelled_subscription_past_its_period_end_is_not_resumable(): void
    {
        $user = $this->user();
        $plan = $this->plan('Pro', 2);
        $sub = $this->localSubscription($user, $plan);
        $sub->update(['status' => GatewaySubscription::STATUS_CANCELLED, 'cancelled_at' => now()->subDays(40), 'current_period_end' => now()->subDay()]);

        $this->actingAs($user)
            ->post('/subscription/resume')
            ->assertSessionHas('error');

        $this->assertSame(GatewaySubscription::STATUS_CANCELLED, $sub->fresh()->status);
    }

    // ─── downgrade ───────────────────────────────

    public function test_downgrade_to_free_ends_the_plan_at_period_end(): void
    {
        $user = $this->user();
        $paid = $this->plan('Pro', 2);
        $free = $this->plan('Free', 0, 0, true);
        $sub = $this->localSubscription($user, $paid);

        $this->actingAs($user)
            ->post('/subscription/downgrade', ['plan' => $free->slug, 'billing' => 'monthly'])
            ->assertSessionHas('success');

        // A one-time gateway can't auto-bill a lower plan, so a downgrade to Free
        // becomes a cancel-at-period-end (access kept until the period ends).
        $sub->refresh();
        $this->assertSame(GatewaySubscription::STATUS_CANCELLED, $sub->status);
        $this->assertNull($sub->scheduled_plan_id);
    }

    public function test_downgrade_to_a_lower_paid_plan_on_a_one_time_gateway_ends_at_period_end(): void
    {
        $user = $this->user();
        $business = $this->plan('Business', 3, 20);
        $lower = $this->plan('Starter', 1, 5);
        $sub = $this->localSubscription($user, $business);

        $this->actingAs($user)
            ->post('/subscription/downgrade', ['plan' => $lower->slug, 'billing' => 'monthly'])
            ->assertSessionHas('success');

        // No remote sub to swap → ends at period end → Free; nothing is scheduled to
        // silently auto-bill a plan the gateway can't charge.
        $sub->refresh();
        $this->assertSame(GatewaySubscription::STATUS_CANCELLED, $sub->status);
        $this->assertNull($sub->scheduled_plan_id);
    }

    public function test_downgrade_rejects_a_higher_plan(): void
    {
        $user = $this->user();
        $current = $this->plan('Starter', 1, 5);
        $higher = $this->plan('Business', 3, 20);
        $this->localSubscription($user, $current);

        $this->actingAs($user)
            ->post('/subscription/downgrade', ['plan' => $higher->slug, 'billing' => 'monthly'])
            ->assertSessionHas('error');
    }

    public function test_downgrade_to_a_lifetime_cycle_is_refused(): void
    {
        $user = $this->user();
        $current = $this->plan('Business', 3, 20);
        $lower = $this->plan('Starter', 1, 5);
        $this->localSubscription($user, $current);

        $this->actingAs($user)
            ->post('/subscription/downgrade', ['plan' => $lower->slug, 'billing' => 'lifetime'])
            ->assertSessionHas('error');
    }

    // ─── upgrade (one-time gateway routes through checkout) ──

    public function test_upgrade_on_a_one_time_gateway_redirects_to_checkout(): void
    {
        $user = $this->user();
        $current = $this->plan('Starter', 1, 5);
        $higher = $this->plan('Business', 3, 20);
        $this->localSubscription($user, $current);

        $this->actingAs($user)
            ->post('/subscription/upgrade', ['plan' => $higher->slug, 'billing' => 'monthly'])
            ->assertRedirect(route('checkout.show', ['plan' => $higher->slug, 'billing' => 'monthly']));
    }

    public function test_upgrade_to_lifetime_always_routes_through_checkout(): void
    {
        $user = $this->user();
        $current = $this->plan('Starter', 1, 5);
        $higher = $this->plan('Business', 3, 20);
        $this->localSubscription($user, $current);

        $this->actingAs($user)
            ->post('/subscription/upgrade', ['plan' => $higher->slug, 'billing' => 'lifetime'])
            ->assertRedirect(route('checkout.show', ['plan' => $higher->slug, 'billing' => 'lifetime']));
    }

    public function test_upgrade_without_a_subscription_errors(): void
    {
        $higher = $this->plan('Business', 3, 20);

        $this->actingAs($this->user())
            ->post('/subscription/upgrade', ['plan' => $higher->slug, 'billing' => 'monthly'])
            ->assertSessionHas('error');
    }

    // ─── cancel scheduled change ─────────────────

    public function test_cancel_scheduled_change_clears_the_schedule(): void
    {
        $user = $this->user();
        $current = $this->plan('Business', 3, 20);
        $target = $this->plan('Starter', 1, 5);
        $sub = $this->localSubscription($user, $current);
        $sub->update([
            'scheduled_plan_id' => $target->id,
            'scheduled_billing_cycle' => 'monthly',
            'scheduled_change_at' => now()->addDays(25),
        ]);

        $this->actingAs($user)
            ->post('/subscription/cancel-scheduled')
            ->assertSessionHas('success');

        $sub->refresh();
        $this->assertNull($sub->scheduled_plan_id);
        $this->assertNull($sub->scheduled_change_at);
    }

    public function test_cancel_scheduled_change_with_none_errors(): void
    {
        $user = $this->user();
        $this->localSubscription($user, $this->plan('Pro', 2));

        $this->actingAs($user)
            ->post('/subscription/cancel-scheduled')
            ->assertSessionHas('error');
    }
}
