<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoModeExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The `public` throttle category is keyed by IP, which is 127.0.0.1 for every test. When
     * Redis is up the counter is a Redis key that RefreshDatabase does not roll back, so a
     * test that deliberately trips it would bleed 429s into every later test on a
     * public-throttled route. Clear it on both ends.
     */
    protected function setUp(): void
    {
        parent::setUp();

        app(\App\Services\RateLimiterService::class)->clear('public', '127.0.0.1');
    }

    protected function tearDown(): void
    {
        app(\App\Services\RateLimiterService::class)->clear('public', '127.0.0.1');

        parent::tearDown();
    }

    private function user(): User
    {
        return User::create([
            'name' => 'Demo Creator',
            'email' => 'creator@usage.test',
            'password' => 'secret-hash',
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);
    }

    public function test_usage_export_is_not_blocked_by_demo_mode(): void
    {
        config(['demo.enabled' => true]);

        $response = $this->actingAs($this->user())
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->post('/user/dashboard/usage/export');

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            (string) $response->headers->get('Content-Type'),
            'the export must return a spreadsheet, not a JSON error the client saves as .xlsx'
        );
    }

    public function test_usage_export_is_rate_limited(): void
    {
        $user = $this->user();

        // 10 per 60s, so the eleventh call in the window is refused.
        foreach (range(1, 10) as $ignored) {
            $this->actingAs($user)
                ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
                ->post('/user/dashboard/usage/export')
                ->assertOk();
        }

        $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->postJson('/user/dashboard/usage/export')
            ->assertStatus(429)
            ->assertJson(['code' => 'RATE_LIMITED']);
    }

    public function test_destructive_writes_are_still_blocked_in_demo_mode(): void
    {
        config(['demo.enabled' => true]);

        // The allowlist must not have opened the generic block: a real write still 403s.
        $this->actingAs($this->user())
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->postJson('/user/dashboard/tool-embeds', ['tool_slug' => 'anything'])
            ->assertForbidden();
    }
}
