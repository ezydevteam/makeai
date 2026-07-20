<?php

namespace Tests\Feature;

use App\Models\RateLimitRule;
use App\Services\RateLimiterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Phase 1 of the settings refactor: the rate-limit tier matrix now lives in the
 * rate_limit_rules table instead of flat rl_* settings rows.
 */
class RateLimitRuleTest extends TestCase
{
    use RefreshDatabase;

    private function limitFor(string $category, string $tier): array
    {
        // getLimitForTier is private; exercise it through the public getDefaults path
        // by reflecting, keeping the test focused on the table-backed resolution.
        $service = app(RateLimiterService::class);
        $method = new \ReflectionMethod($service, 'getLimitForTier');

        return $method->invoke($service, $category, $tier, null);
    }

    public function test_service_reads_limits_from_the_rate_limit_rules_table(): void
    {
        RateLimitRule::create([
            'category' => 'text_gen',
            'tier' => 'guest',
            'max_attempts' => 7,
            'window_seconds' => 111,
        ]);

        $limit = $this->limitFor('text_gen', 'guest');

        $this->assertSame(7, $limit['max_attempts']);
        $this->assertSame(111, $limit['window_seconds']);
    }

    public function test_service_falls_back_to_coded_defaults_when_no_row_exists(): void
    {
        $this->assertSame(0, RateLimitRule::count());

        $defaults = app(RateLimiterService::class)->getDefaults();
        $limit = $this->limitFor('text_gen', 'pro_user');

        $this->assertSame($defaults['text_gen']['pro_user'], $limit);
    }

    public function test_map_is_cached_and_invalidated_on_write(): void
    {
        RateLimitRule::create([
            'category' => 'auth',
            'tier' => 'guest',
            'max_attempts' => 3,
            'window_seconds' => 60,
        ]);

        $this->assertSame(3, RateLimitRule::map()['auth']['guest']['max_attempts']);
        $this->assertTrue(Cache::has('rate_limit_rules:map'));

        // A write must bust the cache so the next read reflects it.
        RateLimitRule::where('category', 'auth')->where('tier', 'guest')->first()
            ->update(['max_attempts' => 9]);

        $this->assertSame(9, RateLimitRule::map()['auth']['guest']['max_attempts']);
    }

    public function test_admin_update_persists_tiers_to_the_table(): void
    {
        $service = app(RateLimiterService::class);
        $controller = new \App\Http\Controllers\Admin\System\RateLimitController($service);

        $request = \Illuminate\Http\Request::create('/admin/system/rate-limits/tiers', 'PUT', [
            'tiers' => [[
                'category' => 'contact',
                'guest_max' => 2, 'guest_window' => 120,
                'free_max' => 4, 'free_window' => 120,
                'pro_max' => 8, 'pro_window' => 120,
            ]],
        ]);

        $controller->updateTiers($request);

        $this->assertDatabaseHas('rate_limit_rules', [
            'category' => 'contact', 'tier' => 'guest', 'max_attempts' => 2, 'window_seconds' => 120,
        ]);
        $this->assertDatabaseHas('rate_limit_rules', [
            'category' => 'contact', 'tier' => 'pro_user', 'max_attempts' => 8, 'window_seconds' => 120,
        ]);
    }
}
