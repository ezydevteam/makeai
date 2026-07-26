<?php

namespace Tests\Feature;

use App\Models\AffiliateCommission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AffiliateCommissionFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Settings are cached with rememberForever and the array store outlives RefreshDatabase,
     * so the extended license this test switches on would leak into later tests.
     */
    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    private function props(string $url): array
    {
        $user = User::where('email', config('demo.user_email'))->firstOrFail();

        return $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get($url)
            ->assertOk()
            ->viewData('page')['props'];
    }

    public function test_every_commission_status_tab_has_rows(): void
    {
        config([
            'demo.admin_password' => 'demo-admin-password',
            'demo.user_password' => 'demo-user-password',
            'broadcasting.default' => 'null',
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);

        settings_set('license_type', 2, 'integer', 'license');
        settings_set('affiliate_enabled', '1', 'boolean', 'affiliate');

        $user = User::where('email', config('demo.user_email'))->firstOrFail();

        // The seeder must cover all five states the UI offers as filters.
        foreach (['pending', 'approved', 'paid', 'rejected', 'cancelled'] as $status) {
            $this->assertGreaterThan(
                0,
                AffiliateCommission::where('referrer_id', $user->id)->where('status', $status)->count(),
                "no {$status} commissions seeded — that filter tab is permanently empty"
            );
        }

        // And the page must actually return them: the filter is applied server-side, so a
        // status whose rows sit past page 1 still shows up.
        foreach (['pending', 'approved', 'paid', 'rejected', 'cancelled'] as $status) {
            $props = $this->props('/user/dashboard/affiliate?status=' . $status);

            $this->assertNotEmpty($props['commissions']['data'], "the {$status} tab returned no rows");
            $this->assertSame($status, $props['filters']['status']);
            $this->assertTrue(
                collect($props['commissions']['data'])->every(fn ($row) => $row['status'] === $status),
                "the {$status} tab leaked rows of another status"
            );
        }
    }

    public function test_commission_pagination_keeps_the_status_filter(): void
    {
        $user = User::create([
            'name' => 'Affiliate',
            'email' => 'affiliate@test.test',
            'password' => 'secret-hash',
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
            'referral_code' => 'AFFTEST1',
        ]);

        settings_set('license_type', 2, 'integer', 'license');
        settings_set('affiliate_enabled', '1', 'boolean', 'affiliate');

        // 15 paid commissions: more than one page at 10 per page.
        foreach (range(1, 15) as $index) {
            AffiliateCommission::create([
                'referrer_id' => $user->id,
                'referred_id' => $user->id,
                'order_id' => null,
                'amount' => 10 + $index,
                'status' => 'paid',
                'approved_at' => now()->subDays($index),
                'paid_at' => now()->subDays($index),
            ]);
        }

        $props = $this->actingAs($user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard/affiliate?status=paid')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(15, $props['commissions']['total']);
        $this->assertSame(2, $props['commissions']['last_page']);
        $this->assertSame(1, $props['commissions']['from']);
        $this->assertSame(10, $props['commissions']['to']);

        $next = collect($props['commissions']['links'])->firstWhere('label', '2');
        $this->assertNotNull($next);
        $this->assertStringContainsString('status=paid', $next['url']);
        // Its own page parameter, so paging commissions does not move the other two lists.
        $this->assertStringContainsString('commissions_page=2', $next['url']);
    }

    public function test_referrals_are_paginated_on_their_own_page_parameter(): void
    {
        config([
            'demo.admin_password' => 'demo-admin-password',
            'demo.user_password' => 'demo-user-password',
            'broadcasting.default' => 'null',
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);

        settings_set('license_type', 2, 'integer', 'license');
        settings_set('affiliate_enabled', '1', 'boolean', 'affiliate');

        $user = User::where('email', config('demo.user_email'))->firstOrFail();
        $total = \App\Models\AffiliateReferral::where('referrer_id', $user->id)->count();

        // The showcase account has far more referrals than the old ->limit(50) exposed.
        $this->assertGreaterThan(50, $total);

        $props = $this->props('/user/dashboard/affiliate');

        $this->assertCount(10, $props['referrals']['data']);
        $this->assertSame($total, $props['referrals']['total']);
        $this->assertGreaterThan(1, $props['referrals']['last_page']);

        $next = collect($props['referrals']['links'])->firstWhere('label', '2');
        $this->assertNotNull($next);
        $this->assertStringContainsString('referrals_page=2', $next['url']);

        // Paging referrals must not drag the commissions list along with it.
        $page2 = $this->props('/user/dashboard/affiliate?referrals_page=2');

        $this->assertSame(2, $page2['referrals']['current_page']);
        $this->assertSame(1, $page2['commissions']['current_page']);

        // Page 2 is a different slice. Compared via from/to rather than the rows themselves:
        // most referrals are anonymous clicks with no referred user, so their email column is
        // the literal "Pending" on every page and would compare equal.
        $this->assertSame(1, $props['referrals']['from']);
        $this->assertSame(10, $props['referrals']['to']);
        $this->assertSame(11, $page2['referrals']['from']);
        $this->assertSame(20, $page2['referrals']['to']);
    }
}
