<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the whole seeder against the mass-assignment trap that flattened it: none of
 * these models list created_at in $fillable, so a 'created_at' passed to create() /
 * updateOrCreate() is silently dropped and the row lands on today. When that happens
 * every demo chart collapses into a single spike at "now", which is invisible in a
 * diff and only shows up on the dashboard.
 */
class DemoSeederTimelineSpreadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * table => [oldest row is at least N days back, at least N distinct calendar days]
     *
     * Thresholds sit well under what the seeder actually produces, so random spreads
     * cannot make this flaky — they only fail if backdating stops working.
     */
    private const EXPECTED_SPREAD = [
        'users' => [300, 30],
        'payments' => [300, 60],
        'ai_usage_logs' => [300, 100],
        'blog_posts' => [300, 15],
        'comments' => [200, 40],
        'comment_reports' => [200, 20],
        'login_history' => [60, 25],
        'tool_reviews' => [120, 60],
        'ai_chats' => [25, 4],
        'ai_chat_messages' => [25, 4],
        'affiliate_referrals' => [300, 40],
        'affiliate_commissions' => [300, 10],
        // Only 5 rows, each on a random day within the last 10 — they can genuinely collide
        // onto 2 calendar days, so anything above that is a flaky threshold, not a signal.
        'contact_messages' => [1, 2],
        'testimonials' => [60, 6],
        'sms_campaign_recipients' => [5, 2],
        'documents' => [2, 2],
        'credit_transactions' => [20, 6],
        'support_tickets' => [20, 8],
        'support_ticket_replies' => [20, 8],
    ];

    public function test_seeded_rows_are_spread_over_time(): void
    {
        config([
            'demo.admin_password' => 'demo-admin-password',
            'demo.user_password' => 'demo-user-password',
            'broadcasting.default' => 'null',
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);

        foreach (self::EXPECTED_SPREAD as $table => [$minDaysBack, $minDistinctDays]) {
            $timestamps = DB::table($table)->pluck('created_at')->filter();

            $this->assertNotEmpty($timestamps, "{$table} seeded no rows");

            $days = $timestamps->map(fn ($value) => substr((string) $value, 0, 10))->unique();

            $this->assertGreaterThanOrEqual(
                $minDistinctDays,
                $days->count(),
                "{$table} rows land on only {$days->count()} calendar day(s) — backdating is being dropped"
            );

            $oldest = substr((string) $timestamps->min(), 0, 10);
            $this->assertLessThanOrEqual(
                now()->subDays($minDaysBack)->toDateString(),
                $oldest,
                "{$table}'s oldest row is {$oldest}, which is not far enough back"
            );

            $this->assertNotSame(
                [now()->toDateString()],
                $days->values()->all(),
                "every {$table} row was written today"
            );
        }
    }
}
