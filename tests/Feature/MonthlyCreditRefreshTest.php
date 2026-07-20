<?php

namespace Tests\Feature;

use App\Models\GatewaySubscription;
use App\Models\Plan;
use App\Models\User;
use App\Services\Subscription\SubscriptionLifecycleService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Plans advertise "Monthly credits", but the allowance was only granted on activation and
 * on a gateway renewal — so a YEARLY subscriber got one month's credits per year and a
 * LIFETIME buyer got them exactly once, then ran dry.
 *
 * The refresh is anchored to each subscription's OWN anniversary, not the calendar month:
 * someone who buys on the 20th refreshes on the 20th. A calendar reset would hand a buyer
 * on the 31st a second allowance the next day.
 */
class MonthlyCreditRefreshTest extends TestCase
{
    private const ALLOWANCE = 1000;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
    }

    private function service(): SubscriptionLifecycleService
    {
        return app(SubscriptionLifecycleService::class);
    }

    private function plan(): Plan
    {
        return Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 10, 'price_yearly' => 100, 'credits' => self::ALLOWANCE,
            'is_active' => true, 'is_free' => false, 'sort_order' => 1, 'vat_percentage' => 0,
        ]);
    }

    /**
     * A subscriber who activated at $activatedAt and has since spent down to 50 credits.
     */
    private function subscriber(Carbon $activatedAt, string $cycle = 'yearly', ?Carbon $periodEnd = null, string $status = GatewaySubscription::STATUS_ACTIVE): GatewaySubscription
    {
        $user = User::factory()->create(['is_active' => true, 'credits' => 50]);

        return GatewaySubscription::create([
            'user_id' => $user->id,
            'plan_id' => $this->plan()->id,
            'billing_cycle' => $cycle,
            'status' => $status,
            'gateway' => 'stripe',
            'amount' => 100,
            'currency' => 'USD',
            'current_period_start' => $activatedAt,
            'current_period_end' => $periodEnd ?? $activatedAt->copy()->addYear(),
            'credits_refreshed_at' => $activatedAt,
        ]);
    }

    private function creditsOf(GatewaySubscription $subscription): float
    {
        return (float) $subscription->user->fresh()->credits;
    }

    public function test_a_yearly_subscriber_is_topped_up_every_month_not_once_a_year(): void
    {
        $this->travelTo(Carbon::parse('2026-07-20 10:00'));
        $subscription = $this->subscriber(now());

        // One month on, they hit their anniversary.
        $this->travelTo(Carbon::parse('2026-08-20 10:30'));
        $this->assertSame(1, $this->service()->refreshMonthlyCredits());
        $this->assertSame((float) self::ALLOWANCE, $this->creditsOf($subscription));

        // Spend it down again; the following month tops them back up.
        $subscription->user->update(['credits' => 50]);
        $this->travelTo(Carbon::parse('2026-09-20 10:30'));
        $this->assertSame(1, $this->service()->refreshMonthlyCredits());
        $this->assertSame((float) self::ALLOWANCE, $this->creditsOf($subscription));
    }

    public function test_a_lifetime_buyer_keeps_getting_monthly_credits(): void
    {
        $this->travelTo(Carbon::parse('2026-07-20 10:00'));

        // A lifetime plan has a NULL period end — it never lapses.
        $subscription = $this->subscriber(now(), 'lifetime', periodEnd: null);
        $subscription->update(['current_period_end' => null]);

        $this->travelTo(Carbon::parse('2026-08-20 10:30'));

        $this->assertSame(1, $this->service()->refreshMonthlyCredits());
        $this->assertSame((float) self::ALLOWANCE, $this->creditsOf($subscription));
    }

    public function test_nothing_is_granted_before_the_anniversary(): void
    {
        $this->travelTo(Carbon::parse('2026-07-20 10:00'));
        $subscription = $this->subscriber(now());

        // 29 days in — not yet a month.
        $this->travelTo(Carbon::parse('2026-08-18 23:59'));

        $this->assertSame(0, $this->service()->refreshMonthlyCredits());
        $this->assertSame(50.0, $this->creditsOf($subscription));
    }

    public function test_a_calendar_month_rollover_does_not_grant_credits(): void
    {
        // The abuse case: buy on the 31st, and a calendar-month reset would hand out a
        // second allowance the very next day. The anniversary is what counts.
        $this->travelTo(Carbon::parse('2026-07-31 09:00'));
        $subscription = $this->subscriber(now());

        $this->travelTo(Carbon::parse('2026-08-01 00:05'));
        $this->assertSame(0, $this->service()->refreshMonthlyCredits());
        $this->assertSame(50.0, $this->creditsOf($subscription));

        // Due on the 31st, a full month after purchase.
        $this->travelTo(Carbon::parse('2026-08-31 09:30'));
        $this->assertSame(1, $this->service()->refreshMonthlyCredits());
        $this->assertSame((float) self::ALLOWANCE, $this->creditsOf($subscription));
    }

    public function test_a_month_end_anniversary_survives_a_short_month(): void
    {
        // Jan 31 → Feb 28 (no overflow), and then back to Mar 31 — the anniversary must not
        // get stuck on the 28th, which is what chaining off each refresh would do.
        $this->travelTo(Carbon::parse('2027-01-31 09:00'));
        $subscription = $this->subscriber(now());

        $this->travelTo(Carbon::parse('2027-02-28 09:30'));
        $this->assertSame(1, $this->service()->refreshMonthlyCredits());
        $this->assertSame('2027-02-28', $subscription->fresh()->credits_refreshed_at->toDateString());

        // Mar 30 is NOT yet the anniversary — it is still the 31st.
        $subscription->user->update(['credits' => 50]);
        $this->travelTo(Carbon::parse('2027-03-30 09:30'));
        $this->assertSame(0, $this->service()->refreshMonthlyCredits());

        $this->travelTo(Carbon::parse('2027-03-31 09:30'));
        $this->assertSame(1, $this->service()->refreshMonthlyCredits());
        $this->assertSame('2027-03-31', $subscription->fresh()->credits_refreshed_at->toDateString());
    }

    public function test_running_twice_in_the_same_month_grants_only_once(): void
    {
        $this->travelTo(Carbon::parse('2026-07-20 10:00'));
        $subscription = $this->subscriber(now());

        $this->travelTo(Carbon::parse('2026-08-20 10:30'));
        $this->assertSame(1, $this->service()->refreshMonthlyCredits());

        // The scheduler runs hourly — every later run this month must be a no-op.
        $subscription->user->update(['credits' => 50]);
        $this->travelTo(Carbon::parse('2026-08-20 11:30'));
        $this->assertSame(0, $this->service()->refreshMonthlyCredits());
        $this->assertSame(50.0, $this->creditsOf($subscription));
    }

    public function test_a_stalled_cron_is_not_owed_the_missed_months_as_a_lump_sum(): void
    {
        $this->travelTo(Carbon::parse('2026-07-20 10:00'));
        $subscription = $this->subscriber(now());

        // Cron down for three months, then catches up: the wallet is topped UP to the
        // allowance, not credited 3× it.
        $this->travelTo(Carbon::parse('2026-10-20 10:30'));

        $this->assertSame(1, $this->service()->refreshMonthlyCredits());
        $this->assertSame((float) self::ALLOWANCE, $this->creditsOf($subscription));
        $this->assertSame('2026-10-20', $subscription->fresh()->credits_refreshed_at->toDateString());
    }

    public function test_a_monthly_subscriber_is_not_topped_up_twice_by_the_cron(): void
    {
        $this->travelTo(Carbon::parse('2026-07-20 10:00'));
        $subscription = $this->subscriber(now(), 'monthly', periodEnd: now()->copy()->addMonth());

        // The gateway renewal grants the allowance and restamps the clock…
        $this->travelTo(Carbon::parse('2026-08-20 10:00'));
        $subscription->update(['credits_refreshed_at' => now(), 'current_period_start' => now()]);
        $subscription->user->update(['credits' => self::ALLOWANCE]);

        // …so the cron, running an hour later, must find nothing to do.
        $this->travelTo(Carbon::parse('2026-08-20 11:00'));
        $this->assertSame(0, $this->service()->refreshMonthlyCredits());
    }

    public function test_lapsed_and_unpaid_subscriptions_get_nothing(): void
    {
        $this->travelTo(Carbon::parse('2026-07-20 10:00'));

        $expired = $this->subscriber(now(), 'yearly', periodEnd: now()->copy()->addMonths(2), status: GatewaySubscription::STATUS_EXPIRED);
        $pastDue = $this->subscriber(now(), 'yearly', status: GatewaySubscription::STATUS_PAST_DUE);

        $this->travelTo(Carbon::parse('2026-08-20 10:30'));

        $this->assertSame(0, $this->service()->refreshMonthlyCredits());
        $this->assertSame(50.0, $this->creditsOf($expired));
        $this->assertSame(50.0, $this->creditsOf($pastDue));
    }

    public function test_a_cancelled_subscriber_still_gets_credits_for_the_months_they_paid_for(): void
    {
        $this->travelTo(Carbon::parse('2026-07-20 10:00'));

        // Cancelled a yearly plan — access (and credits) run to the end of the paid year.
        $subscription = $this->subscriber(now(), 'yearly', status: GatewaySubscription::STATUS_CANCELLED);

        $this->travelTo(Carbon::parse('2026-08-20 10:30'));

        $this->assertSame(1, $this->service()->refreshMonthlyCredits());
        $this->assertSame((float) self::ALLOWANCE, $this->creditsOf($subscription));
    }
}
