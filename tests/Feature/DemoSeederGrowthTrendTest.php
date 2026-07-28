<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The demo has to look like a business that is growing.
 *
 * Every KPI card on the admin dashboard — and the creator dashboard's own usage chart —
 * compares a window against the window immediately before it. A flat spread puts all of
 * those on ~0%, and a partially-seeded today puts them negative, so the demo advertised
 * the product with red arrows on every card.
 *
 * These assertions mirror DashboardController's own window arithmetic. If someone
 * flattens the seeder's volume curve again, or reintroduces a `$hour <= now()->hour`
 * loop that truncates today, this fails.
 */
class DemoSeederGrowthTrendTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedDemo(): void
    {
        config([
            'demo.admin_password' => 'demo-admin-password',
            'demo.user_password' => 'demo-user-password',
            'broadcasting.default' => 'null',
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);
    }

    /**
     * The same boundaries DashboardController builds for its card comparisons.
     *
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon}
     */
    private function windows(int $days): array
    {
        $now = now();

        return [
            $now->copy()->subDays($days - 1)->startOfDay(),
            $now->copy()->endOfDay(),
            $now->copy()->subDays($days * 2 - 1)->startOfDay(),
            $now->copy()->subDays($days)->endOfDay(),
        ];
    }

    public function test_every_dashboard_period_comparison_shows_growth(): void
    {
        $this->seedDemo();

        // The four periods the admin dashboard's card comparisons offer.
        foreach ([1, 7, 30, 90] as $days) {
            [$currentStart, $currentEnd, $previousStart, $previousEnd] = $this->windows($days);

            $signupsCurrent = User::whereBetween('created_at', [$currentStart, $currentEnd])->count();
            $signupsPrevious = User::whereBetween('created_at', [$previousStart, $previousEnd])->count();
            $this->assertGreaterThan(
                $signupsPrevious,
                $signupsCurrent,
                "Signups over {$days}d ({$signupsCurrent}) must beat the preceding {$days}d ({$signupsPrevious})"
            );

            $requestsCurrent = AiUsageLog::whereBetween('created_at', [$currentStart, $currentEnd])->count();
            $requestsPrevious = AiUsageLog::whereBetween('created_at', [$previousStart, $previousEnd])->count();
            $this->assertGreaterThan(
                $requestsPrevious,
                $requestsCurrent,
                "AI requests over {$days}d ({$requestsCurrent}) must beat the preceding {$days}d ({$requestsPrevious})"
            );

            $revenueCurrent = (float) Payment::where('status', 'completed')
                ->whereBetween('created_at', [$currentStart, $currentEnd])->sum('amount');
            $revenuePrevious = (float) Payment::where('status', 'completed')
                ->whereBetween('created_at', [$previousStart, $previousEnd])->sum('amount');
            $this->assertGreaterThan(
                $revenuePrevious,
                $revenueCurrent,
                "Revenue over {$days}d ({$revenueCurrent}) must beat the preceding {$days}d ({$revenuePrevious})"
            );
        }
    }

    /**
     * demo:reset runs on a six-hourly schedule, so it lands at 00:00, 06:00, 12:00 and
     * 18:00 — and the old loops seeded today only up to the current hour, with the
     * showcase account's starting at 08:00. A reset just after midnight therefore left
     * today all but empty while yesterday was a full day, which is the exact shape that
     * made every "today" card open on a fall.
     */
    public function test_today_is_fully_seeded_when_the_reset_runs_just_after_midnight(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(0, 5));

        $this->seedDemo();

        $todayCount = AiUsageLog::whereDate('created_at', now()->toDateString())->count();
        $yesterdayCount = AiUsageLog::whereDate('created_at', now()->subDay()->toDateString())->count();

        $this->assertGreaterThan(
            $yesterdayCount,
            $todayCount,
            "A 00:05 reset must still fill today ({$todayCount} rows) past yesterday ({$yesterdayCount})"
        );

        // The creator dashboard reads its chart from the showcase account's own ledger,
        // which is derived from that account's usage logs — so it has to be busy today too.
        $showcase = User::where('email', config('demo.user_email', 'user@demo.com'))->first();

        if ($showcase) {
            $showcaseToday = AiUsageLog::where('user_id', $showcase->id)
                ->whereDate('created_at', now()->toDateString())->count();

            $this->assertGreaterThan(
                1,
                $showcaseToday,
                'The showcase account must have a full day of usage today, not the single fallback row'
            );
        }
    }

    /**
     * The seeder pins mt_srand(RANDOM_SEED) so a demo is reproducible. The old today-loops
     * ran a clock-dependent NUMBER of iterations, and every iteration consumed mt_rand()
     * draws — which shifted each later draw in the file, so the "fixed" seed still produced
     * a different dataset at 03:00 than at 21:00.
     *
     * Asserted on the day-spreading helpers directly rather than by seeding twice: both the
     * count of rows and the count of random draws consumed must be identical across hours.
     */
    public function test_day_spread_is_identical_whatever_hour_the_reset_ran(): void
    {
        $seeder = new DemoSeeder;
        $spread = new \ReflectionMethod($seeder, 'spreadOverDay');
        $volume = new \ReflectionMethod($seeder, 'dailyVolume');

        $sample = function () use ($seeder, $spread, $volume) {
            mt_srand(12345);

            return collect(range(0, 3))->map(fn (int $daysAgo) => [
                'volume' => $volume->invoke($seeder, $daysAgo),
                'moments' => collect($spread->invoke($seeder, $daysAgo, 9, 7, 22))
                    ->map(fn ($moment) => $moment->format('H:i'))->all(),
                // Proves the draw count matched too: a diverged stream changes this.
                'streamPosition' => mt_rand(),
            ])->all();
        };

        Carbon::setTestNow(Carbon::today()->setTime(3, 0));
        $early = $sample();

        Carbon::setTestNow(Carbon::today()->setTime(21, 0));
        $late = $sample();

        $this->assertSame($early, $late);
    }
}
