<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\GenerationHistory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Usage dashboard buckets ai_usage_logs by hour (1D), day (7D / 1M) and month (1Y).
 * Those queries use MySQL-only date functions, so this asserts against the rows themselves
 * rather than the endpoint — same buckets, same conclusion: no period comes up empty.
 */
class DemoUsageTimelineCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_showcase_usage_covers_every_chart_period(): void
    {
        // Seeding a year of data takes long enough that the clock can cross an hour boundary
        // mid-test, which would move the "current hour" away from the row seeded into it.
        $this->freezeTime();

        config([
            'demo.admin_password' => 'demo-admin-password',
            'demo.user_password' => 'demo-user-password',
            'broadcasting.default' => 'null',
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);

        // A stale Usage-page snapshot from before a reseed must not survive it: the page
        // caches its stats for 5 minutes and only real generations bust that key.
        $staleUser = User::where('email', config('demo.user_email'))->firstOrFail();
        \Illuminate\Support\Facades\Cache::put("usage_stats_{$staleUser->id}", ['total_generations' => 1], 300);

        // demo:reset re-runs the seeder; the account's history must not stack up.
        $this->seed(DemoSeeder::class);

        $this->assertNull(
            \Illuminate\Support\Facades\Cache::get("usage_stats_{$staleUser->id}"),
            're-seeding must drop the cached Usage stats, or the page serves the old dataset'
        );

        $user = User::where('email', config('demo.user_email'))->firstOrFail();
        $logs = AiUsageLog::where('user_id', $user->id)->get();

        $this->assertGreaterThan(100, $logs->count(), 'the timeline should span a year of work');

        // 1D — the rolling 24-hour window, including the bucket the chart marks current.
        $lastDay = $logs->filter(fn ($log) => $log->created_at->gte(now()->subHours(24)));
        $this->assertNotEmpty($lastDay, '1D window has no generations');
        $this->assertTrue(
            $lastDay->contains(fn ($log) => $log->created_at->isCurrentHour()),
            "the current hour is empty, so 1D's highlighted bucket has no value"
        );

        // 7D and 1M — every calendar day must have something.
        foreach ([7, 30] as $days) {
            for ($day = 0; $day < $days; $day++) {
                $date = now()->subDays($day)->toDateString();
                $this->assertTrue(
                    $logs->contains(fn ($log) => $log->created_at->toDateString() === $date),
                    "no generations on {$date} — the {$days}-day chart has a gap"
                );
            }
        }

        // 1Y — every calendar month.
        for ($month = 0; $month < 12; $month++) {
            $bucket = now()->subMonths($month)->format('Y-m');
            $this->assertTrue(
                $logs->contains(fn ($log) => $log->created_at->format('Y-m') === $bucket),
                "no generations in {$bucket} — the 1Y chart has a gap"
            );
        }

        // Top tools ranks something real rather than a flat tie.
        $byTool = $logs->groupBy('tool_slug')->map->count()->sortDesc();
        $this->assertGreaterThanOrEqual(3, $byTool->count(), 'too few tools for a Top tools list');
        $this->assertGreaterThan($byTool->last(), $byTool->first(), 'tool usage is uniform, so the ranking is arbitrary');

        // "Recent activity" on the page, and the History screen, read generation_history.
        $history = GenerationHistory::where('user_id', $user->id)->get();
        $this->assertGreaterThanOrEqual(10, $history->count());
        $this->assertTrue($history->every(fn ($row) => $row->created_at->gte(now()->subDays(15))));

        // The "used today / this month" tiles sit directly above the chart drawn from these
        // logs, so they have to agree with it.
        $user->refresh();
        $this->assertEqualsWithDelta(
            (float) $logs->filter(fn ($log) => $log->created_at->isToday())->sum('credits_used'),
            (float) $user->credits_used_today,
            0.01
        );
        $this->assertEqualsWithDelta(
            (float) $logs->filter(fn ($log) => $log->created_at->gte(now()->startOfMonth()))->sum('credits_used'),
            (float) $user->credits_used_month,
            0.01
        );
    }
}
