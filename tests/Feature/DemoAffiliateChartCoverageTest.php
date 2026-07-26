<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAffiliateChartCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Settings are cached with rememberForever, and the array store outlives RefreshDatabase's
     * rollback — so the extended license this test switches on would otherwise leak into every
     * later test that expects a regular license.
     */
    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\Cache::flush();

        parent::tearDown();
    }

    public function test_every_affiliate_chart_period_has_data(): void
    {
        // The clock must not cross an hour boundary between seeding and asserting, or the
        // 1D chart's "current" bucket moves off the row seeded into it.
        $this->freezeTime();

        config([
            'demo.admin_password' => 'demo-admin-password',
            'demo.user_password' => 'demo-user-password',
            'broadcasting.default' => 'null',
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        // The affiliate area is extended-license only and gated on its own toggle.
        settings_set('license_type', 2, 'integer', 'license');
        settings_set('affiliate_enabled', '1', 'boolean', 'affiliate');

        $user = User::where('email', config('demo.user_email'))->firstOrFail();

        foreach (['1D', '7D', '1M', '1Y'] as $period) {
            $response = $this->actingAs($user)
                ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
                ->getJson('/user/dashboard/affiliate/chart?period=' . $period);

            $response->assertOk();
            $buckets = $response->json();

            $this->assertNotEmpty($buckets, "{$period} returned no buckets");

            $totals = [
                'clicks' => array_sum(array_column($buckets, 'clicks')),
                'registrations' => array_sum(array_column($buckets, 'registrations')),
                'conversions' => array_sum(array_column($buckets, 'conversions')),
            ];

            foreach ($totals as $series => $total) {
                $this->assertGreaterThan(0, $total, "{$period} has no {$series}");
            }

            // The current bucket (this hour / today / this month) must not be the empty one.
            $current = collect($buckets)->firstWhere('is_current', true);
            $this->assertNotNull($current, "{$period} has no current bucket");
            $this->assertGreaterThan(
                0,
                $current['clicks'] + $current['registrations'] + $current['conversions'],
                "{$period}'s current bucket is empty"
            );
        }

        // Daily continuity: every one of the last 7 days has at least a click.
        $week = $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->getJson('/user/dashboard/affiliate/chart?period=7D')
            ->json();

        foreach ($week as $day) {
            $this->assertGreaterThan(0, $day['clicks'], "{$day['label']} has no clicks");
        }

        // Monthly continuity across the year.
        $year = $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->getJson('/user/dashboard/affiliate/chart?period=1Y')
            ->json();

        foreach ($year as $month) {
            $this->assertGreaterThan(0, $month['clicks'], "{$month['label']} has no clicks");
        }

        // Cached totals on the user row agree with the commission ledger.
        $user->refresh();
        $earned = \App\Models\AffiliateCommission::where('referrer_id', $user->id)
            ->whereIn('status', ['approved', 'paid'])
            ->sum('amount');

        $this->assertEqualsWithDelta((float) $earned, (float) $user->referral_earnings, 0.01);
    }
}
