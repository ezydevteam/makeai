<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyLoginHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_page_lists_login_history_the_dashboard_links_to(): void
    {
        $user = User::create([
            'name' => 'Auditor',
            'email' => 'auditor@test.test',
            'password' => 'secret-hash',
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        // 14 sign-ins at 10 per page, one of them failed.
        foreach (range(1, 14) as $index) {
            $login = LoginHistory::create([
                'user_id' => $user->id,
                'ip' => '203.0.113.' . $index,
                'user_agent' => 'DemoBrowser/1.0',
                'country' => 'United States',
                'city' => 'New York',
                'success' => $index !== 3,
            ]);

            $login->forceFill(['created_at' => now()->subDays($index)])->save();
        }

        $props = $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard/privacy')
            ->assertOk()
            ->viewData('page')['props'];

        // The destination of the dashboard's "View all" link must actually carry the history.
        $this->assertCount(10, $props['loginHistory']['data']);
        $this->assertSame(14, $props['loginHistory']['total']);
        $this->assertSame(2, $props['loginHistory']['last_page']);
        $this->assertSame(1, $props['loginHistory']['from']);
        $this->assertSame(10, $props['loginHistory']['to']);
        $this->assertSame('203.0.113.1', $props['loginHistory']['data'][0]['ip'], 'newest first');

        // Failed attempts are part of the audit trail — sessions would never show them.
        $this->assertTrue(collect($props['loginHistory']['data'])->contains(fn ($row) => $row['success'] === false));

        // Its own page parameter, and the links keep the reader at the section they paged.
        $next = collect($props['loginHistory']['links'])->firstWhere('label', '2');
        $this->assertNotNull($next);
        $this->assertStringContainsString('login_page=2', $next['url']);
        $this->assertStringContainsString('#login-history', $next['url']);

        $page2 = $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard/privacy?login_page=2')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(4, $page2['loginHistory']['data']);
        $this->assertSame(11, $page2['loginHistory']['from']);

        // The dashboard panel stays capped at 5, so "View all" shows strictly more.
        $dashboard = $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(5, $dashboard['recentLoginHistory']);
        $this->assertGreaterThan(
            count($dashboard['recentLoginHistory']),
            $props['loginHistory']['total'],
            'the link must lead somewhere with more rows than the panel it came from'
        );
    }
}
