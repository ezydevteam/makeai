<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\CreditTransaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDashboardCoverageTest extends TestCase
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

    public function test_showcase_dashboard_is_populated_and_agrees_with_usage(): void
    {
        // Keeps "today" from moving between seeding and asserting on a slow run.
        $this->freezeTime();

        config([
            'demo.admin_password' => 'demo-admin-password',
            'demo.user_password' => 'demo-user-password',
            'broadcasting.default' => 'null',
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        // The plan card is gated behind the extended license, like the affiliate area.
        settings_set('license_type', 2, 'integer', 'license');
        settings_set('subscriptions_enabled', '1', 'boolean', 'premium');

        $user = User::where('email', config('demo.user_email'))->firstOrFail();

        $props = $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard')
            ->assertOk()
            ->viewData('page')['props'];

        // Every panel has something in it.
        $this->assertNotEmpty($props['recentTransactions'], 'no credit transactions');
        $this->assertNotEmpty($props['recentDocuments'], 'no documents');
        $this->assertNotEmpty($props['recentLoginHistory'], 'no sign-in history');
        $this->assertNotEmpty($props['quickTools'], 'no quick tools');
        $this->assertNotNull($props['plan'], 'no plan card');
        // Replaced the addon-dependent conversations panel/card, so it must be populated on
        // an install that has no addons active.
        $this->assertNotEmpty($props['recentGenerations'], 'no recent generations');
        $this->assertGreaterThan(0, $props['stats']['total_generations']);
        $this->assertGreaterThan(0, $props['stats']['total_documents']);
        $this->assertGreaterThan(0, $props['stats']['total_open_support_tickets']);
        $this->assertGreaterThan(0, $props['stats']['lifetime_credits_used']);
        $this->assertGreaterThan(0, $props['stats']['credits']);
        $this->assertGreaterThan(0, $props['referral']['earnings']);
        $this->assertGreaterThan(0, $props['referral']['count']);

        // The newest sign-in matches the profile's last_login_ip.
        $this->assertSame($user->last_login_ip, $props['recentLoginHistory'][0]['ip']);

        // The panel shows the last 5, though the account has more history than that.
        $this->assertCount(5, $props['recentLoginHistory']);
        $this->assertGreaterThan(
            5,
            \App\Models\LoginHistory::where('user_id', $user->id)->count(),
            'seed more sign-ins than the panel shows, or this assertion proves nothing'
        );

        // Every chart period is continuous — no empty days.
        foreach (['7d', 'month', '90d'] as $period) {
            $chart = $this->actingAs($user)
                ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
                ->getJson('/user/dashboard/chart?period=' . $period)
                ->assertOk()
                ->json('chart');

            $this->assertNotEmpty($chart, "{$period} returned no buckets");

            foreach ($chart as $bucket) {
                $this->assertGreaterThan(0, $bucket['credits'], "{$period}: {$bucket['date']} is empty");
            }
        }

        // The dashboard chart (credit ledger) and the Usage page chart (generation logs) must
        // report the same credits for the same day.
        $logsByDay = AiUsageLog::where('user_id', $user->id)
            ->get(['created_at', 'credits_used'])
            ->groupBy(fn ($log) => $log->created_at->toDateString())
            ->map(fn ($logs) => (float) $logs->sum('credits_used'));

        $ledgerByDay = CreditTransaction::where('user_id', $user->id)
            ->where('type', 'usage')
            ->get(['created_at', 'amount'])
            ->groupBy(fn ($tx) => $tx->created_at->toDateString())
            ->map(fn ($rows) => (float) $rows->sum(fn ($tx) => abs((float) $tx->amount)));

        $this->assertSame($logsByDay->keys()->sort()->values()->all(), $ledgerByDay->keys()->sort()->values()->all());

        foreach ($logsByDay as $date => $credits) {
            $this->assertEqualsWithDelta($credits, $ledgerByDay[$date], 0.01, "{$date} disagrees between the two charts");
        }

        // Balance is the running total of the ledger, and stays positive.
        $this->assertEqualsWithDelta(
            (float) CreditTransaction::where('user_id', $user->id)->sum('amount'),
            (float) $user->credits,
            0.01
        );
        $this->assertGreaterThan(0, (float) $user->credits, 'the showcase account should not be out of credits');
    }
}
