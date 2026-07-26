<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UsageWalletActivityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Spender',
            'email' => 'spender@test.test',
            'password' => 'secret-hash',
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        // 18 ledger rows at 15 per page: one top-up then spends.
        $balance = 0.0;

        foreach (range(1, 18) as $index) {
            $amount = $index === 1 ? 5000 : -random_int(10, 50);
            $balance += $amount;

            $tx = CreditTransaction::create([
                'user_id' => $this->user->id,
                'amount' => $amount,
                'balance_after' => $balance,
                'type' => $index === 1 ? 'purchase' : 'usage',
                'description' => "Entry {$index}",
            ]);

            $tx->forceFill(['created_at' => now()->subDays(20 - $index)])->save();
        }

        // A generation so the stats block has something to summarise too.
        AiUsageLog::create([
            'user_id' => $this->user->id,
            'provider' => 'openai',
            'model' => 'gpt-5.6-terra',
            'type' => 'text_generation',
            'tool_slug' => 'blog-article-generator',
            'input_tokens' => 500,
            'output_tokens' => 300,
            'cost_usd' => 0.01,
            'credits_used' => 20,
            'response_time_ms' => 1200,
            'status' => 'completed',
        ]);
    }

    private function props(string $url): array
    {
        return $this->actingAs($this->user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get($url)
            ->assertOk()
            ->viewData('page')['props'];
    }

    public function test_usage_page_renders_and_carries_the_wallet_ledger(): void
    {
        $props = $this->props('/user/dashboard/usage');

        // The page itself used to 500 on sqlite (MySQL-only HOUR/DAYNAME/YEAR/MONTH).
        $this->assertArrayHasKey('transactions', $props);
        $this->assertCount(15, $props['transactions']['data']);
        $this->assertSame(18, $props['transactions']['total']);
        $this->assertSame(2, $props['transactions']['last_page']);
        $this->assertSame(1, $props['transactions']['from']);
        $this->assertSame(15, $props['transactions']['to']);

        // Newest first, and both directions of money are present.
        $amounts = collect($props['transactions']['data'])->pluck('amount');
        $this->assertTrue($amounts->contains(fn ($amount) => $amount < 0), 'no spend rows');

        // Its own page parameter, anchored so paging keeps the reader at the section.
        $next = collect($props['transactions']['links'])->firstWhere('label', '2');
        $this->assertNotNull($next);
        $this->assertStringContainsString('wallet_page=2', $next['url']);
        $this->assertStringContainsString('#wallet-activity', $next['url']);

        $page2 = $this->props('/user/dashboard/usage?wallet_page=2');
        $this->assertCount(3, $page2['transactions']['data']);
        $this->assertSame(16, $page2['transactions']['from']);

        // The dashboard panel it is reached from shows strictly fewer.
        $dashboard = $this->props('/user/dashboard');
        $this->assertLessThan(
            $props['transactions']['total'],
            count($dashboard['recentTransactions']),
            'the link must lead somewhere with more rows than the panel it came from'
        );
    }

    public function test_stats_use_portable_date_expressions(): void
    {
        $props = $this->props('/user/dashboard/usage');

        // peak_hour and most_active_day come from HOUR()/DAYNAME() equivalents; on sqlite the
        // MySQL-only originals threw, taking the whole page down.
        $this->assertNotNull($props['stats']['peak_hour']);
        $this->assertIsInt($props['stats']['peak_hour']);
        $this->assertContains($props['stats']['most_active_day'], [
            'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday',
        ]);
    }

    public function test_wallet_ledger_is_not_served_from_the_stale_stats_cache(): void
    {
        $this->props('/user/dashboard/usage');

        // The stats payload is cached for 5 minutes; a new purchase must still appear.
        CreditTransaction::create([
            'user_id' => $this->user->id,
            'amount' => 900,
            'balance_after' => 9999,
            'type' => 'purchase',
            'description' => 'Fresh top-up',
        ]);

        $props = $this->props('/user/dashboard/usage');

        $this->assertSame('Fresh top-up', $props['transactions']['data'][0]['description']);
        $this->assertSame(19, $props['transactions']['total']);
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }
}
