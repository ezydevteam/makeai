<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Tests\Feature;

use Addons\AiImagePro\Models\AipAsset;
use Addons\AiImagePro\Models\AipJob;
use Addons\AiImagePro\Services\ImageAccessService;
use Addons\AiImagePro\Services\OperationRegistry;
use App\Models\Setting;
use App\Models\User;
use App\Services\AccessLevelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Tests\TestCase;

/**
 * The addon's promise to the operator is that EVERY behaviour is configurable
 * from the admin panel and nothing is hardcoded. These tests hold that line:
 * the registry must honour admin overrides, and must refuse the one class of
 * override that would let an operator hand a paid engine out for free.
 */
class ImageProTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // AppServiceProvider registers addon providers from the `addons` table, which
        // doesn't exist yet when the app boots under RefreshDatabase — so the addon's
        // migrations are absent unless we activate it and migrate its path by hand.
        \App\Models\Addon::updateOrCreate(['slug' => 'ai-image-pro'], [
            'name' => 'AI Image Pro',
            'version' => '1.0.0',
            'is_active' => true,
            'manifest' => json_decode(
                \Illuminate\Support\Facades\File::get(base_path('addons/ai-image-pro/addon.json')),
                true,
            ),
        ]);

        $this->artisan('migrate', ['--path' => 'addons/ai-image-pro/database/migrations']);
    }

    private function registry(): OperationRegistry
    {
        // Built fresh each time: the registry reads settings on every call, and
        // the settings cache is flushed between writes below.
        return new OperationRegistry(app(AccessLevelService::class));
    }

    private function access(): ImageAccessService
    {
        return new ImageAccessService($this->registry(), app(AccessLevelService::class));
    }

    private function setOperations(array $overrides): void
    {
        addon_setting_set('ai-image-pro', 'operations', $overrides, 'json');
        Setting::flushCache();
    }

    private function setSetting(string $key, mixed $value, string $type = 'string'): void
    {
        addon_setting_set('ai-image-pro', $key, $value, $type);
        Setting::flushCache();
    }

    private function user(array $attrs = []): User
    {
        // is_active must be explicit — the DB default isn't reflected in memory.
        return User::factory()->create(array_merge(['is_active' => true], $attrs));
    }

    // ─── Seeder idempotency ──────────────────────────────────────

    public function test_reactivation_seeder_never_overwrites_operator_landing_edits(): void
    {
        $seeder = new \Addons\AiImagePro\Database\Seeders\ImageProSeeder();

        // First activation seeds the shipped landing copy.
        $seeder->run();
        Setting::flushCache();
        $this->assertSame(
            'AI image generator: create images and photos from text',
            addon_setting('ai-image-pro', 'landing_hero_heading'),
        );

        // Operator customises a landing string.
        addon_setting_set('ai-image-pro', 'landing_hero_heading', 'My Custom Heading', 'string');
        Setting::flushCache();

        // Re-activation (the seeder runs on every activation) must leave it untouched.
        // Regression: the guard used to check Setting::isPersisted('addon_{slug}_{key}')
        // against the core settings table, which no longer holds addon rows post-Phase-2,
        // so it always re-seeded and clobbered the operator value.
        $seeder->run();
        Setting::flushCache();

        $this->assertSame('My Custom Heading', addon_setting('ai-image-pro', 'landing_hero_heading'));
    }

    // ─── OperationRegistry ───────────────────────────────────────

    public function test_ships_operations_across_all_three_tiers(): void
    {
        $registry = $this->registry();

        $this->assertNotEmpty($registry->keysForTier(OperationRegistry::TIER_GENERATE));
        $this->assertNotEmpty($registry->keysForTier(OperationRegistry::TIER_PROVIDER));
        $this->assertNotEmpty($registry->keysForTier(OperationRegistry::TIER_LOCAL));
    }

    public function test_admin_can_reprice_regate_repoint_and_disable_an_operation(): void
    {
        $this->setOperations([
            'upscale' => ['credits' => 99, 'access' => 'guest', 'provider' => 'clipdrop'],
            'crop' => ['enabled' => false],
        ]);

        $registry = $this->registry();

        $this->assertSame(99, $registry->credits('upscale'));
        $this->assertSame('guest', $registry->accessLevel('upscale'));
        $this->assertSame('clipdrop', $registry->engine('upscale'));
        $this->assertFalse($registry->isEnabled('crop'));
    }

    public function test_a_premium_gate_falls_back_when_the_license_has_no_paid_tier(): void
    {
        // On a Regular license isProAvailable() is false, so AccessLevelService drops
        // the `premium` level entirely. Gating an op on a level that no longer exists
        // must fall back to the shipped default rather than fall open to everyone.
        settings_set('license_type', '1', 'integer', 'license');
        settings_set('subscriptions_enabled', '0', 'boolean', 'ai');
        Setting::flushCache();

        $shipped = $this->registry()->accessLevel('upscale');
        $this->setOperations(['upscale' => ['access' => 'premium']]);

        $this->assertSame($shipped, $this->registry()->accessLevel('upscale'));
        $this->assertNotSame('premium', $this->registry()->accessLevel('upscale'));
    }

    public function test_admin_cannot_escalate_an_operations_tier_or_billing_mode(): void
    {
        // The dangerous override: relabel a paid provider op as a free local one.
        // If this were honoured, the operator would be giving away paid API calls.
        $this->setOperations([
            'upscale' => [
                'tier' => OperationRegistry::TIER_LOCAL,
                'billing' => OperationRegistry::BILLING_FREE,
                'inputs' => [],
            ],
        ]);

        $registry = $this->registry();

        $this->assertSame(OperationRegistry::TIER_PROVIDER, $registry->tier('upscale'));
        $this->assertSame(OperationRegistry::BILLING_FLAT, $registry->billing('upscale'));
        $this->assertContains('image', $registry->inputs('upscale'));
    }

    public function test_admin_cannot_point_an_operation_at_an_engine_it_does_not_support(): void
    {
        // remove_bg is not among bg_replace's declared providers.
        $this->setOperations(['bg_replace' => ['provider' => 'remove_bg']]);

        $this->assertNotSame('remove_bg', $this->registry()->engine('bg_replace'));
    }

    public function test_an_unknown_access_level_falls_back_to_the_shipped_default(): void
    {
        // e.g. the admin gated an op to a plan that was later deleted. It must not
        // silently fall open to everyone.
        $shipped = $this->registry()->accessLevel('upscale');

        $this->setOperations(['upscale' => ['access' => 'plan:deleted-plan-xyz']]);

        $this->assertSame($shipped, $this->registry()->accessLevel('upscale'));
    }

    public function test_engine_availability_reflects_the_configured_api_keys(): void
    {
        $this->setSetting('replicate_api_key', '', 'encrypted');
        $this->assertFalse($this->registry()->engineIsConfigured('replicate'));

        $this->setSetting('replicate_api_key', 'r8_test_key', 'encrypted');
        $this->assertTrue($this->registry()->engineIsConfigured('replicate'));

        // Local + core-model engines never need a key of their own.
        $this->assertTrue($this->registry()->engineIsConfigured('gd'));
    }

    public function test_an_operation_without_its_api_key_is_enabled_but_not_usable(): void
    {
        $this->setSetting('replicate_api_key', '', 'encrypted');
        $this->setOperations(['upscale' => ['provider' => 'replicate']]);

        $registry = $this->registry();

        $this->assertTrue($registry->isEnabled('upscale'));
        $this->assertFalse($registry->isUsable('upscale'));
    }

    public function test_media_ops_never_take_a_flat_credit_but_free_ops_can_be_priced(): void
    {
        // A media-billed op is priced per image per model (TokenGuard), never by a
        // flat number here — an admin override on it is ignored.
        $this->setOperations(['generate' => ['credits' => 99]]);
        $this->assertSame(0, $this->registry()->credits('generate'));

        // A free local op is free by default, but an admin can put a price on it
        // (0/blank keeps it free); the credit then applies and makes it chargeable.
        $this->assertSame(0, $this->registry()->credits('resize'));

        $this->setOperations(['resize' => ['credits' => 50]]);
        $this->assertSame(50, $this->registry()->credits('resize'));
        $this->assertTrue($this->registry()->isChargeable('resize'));
    }

    // ─── ImageAccessService ──────────────────────────────────────

    public function test_operation_access_is_gated_by_the_admin_configured_level(): void
    {
        $this->setSetting('remove_bg_api_key', 'key', 'encrypted');
        $this->setOperations(['bg_remove' => ['access' => 'login', 'provider' => 'remove_bg']]);

        $this->assertFalse($this->access()->canRun('bg_remove', null));
        $this->assertTrue($this->access()->canRun('bg_remove', $this->user()));
    }

    public function test_guests_can_run_an_operation_the_admin_opened_to_them(): void
    {
        $this->setOperations(['resize' => ['access' => 'guest']]);
        $this->setSetting('guest_daily_limit', 5, 'integer');

        $this->assertTrue($this->access()->canRun('resize', null));
    }

    public function test_a_zero_guest_daily_limit_shuts_guests_out_entirely(): void
    {
        $this->setSetting('guest_daily_limit', 0, 'integer');

        $this->expectException(HttpResponseException::class);
        $this->access()->assertWithinDailyLimit(null, '203.0.113.10');
    }

    public function test_the_guest_daily_limit_is_enforced_per_ip(): void
    {
        $this->setSetting('guest_daily_limit', 2, 'integer');

        foreach (range(1, 2) as $i) {
            AipJob::create([
                'user_id' => null,
                'guest_ip' => '203.0.113.10',
                'operation' => 'resize',
                'tier' => OperationRegistry::TIER_LOCAL,
                'engine' => 'gd',
                'status' => AipJob::STATUS_COMPLETED,
            ]);
        }

        // A different IP is unaffected.
        $this->access()->assertWithinDailyLimit(null, '203.0.113.99');

        $this->expectException(HttpResponseException::class);
        $this->access()->assertWithinDailyLimit(null, '203.0.113.10');
    }

    public function test_a_zero_user_daily_limit_means_unlimited(): void
    {
        $this->setSetting('user_daily_limit', 0, 'integer');
        $user = $this->user();

        foreach (range(1, 5) as $i) {
            AipJob::create([
                'user_id' => $user->id,
                'operation' => 'resize',
                'tier' => OperationRegistry::TIER_LOCAL,
                'engine' => 'gd',
                'status' => AipJob::STATUS_COMPLETED,
            ]);
        }

        $this->access()->assertWithinDailyLimit($user);
        $this->addToAssertionCount(1); // no exception = unlimited
    }

    public function test_the_user_daily_limit_is_enforced_once_configured(): void
    {
        $this->setSetting('user_daily_limit', 1, 'integer');
        $user = $this->user();

        AipJob::create([
            'user_id' => $user->id,
            'operation' => 'resize',
            'tier' => OperationRegistry::TIER_LOCAL,
            'engine' => 'gd',
            'status' => AipJob::STATUS_COMPLETED,
        ]);

        $this->expectException(HttpResponseException::class);
        $this->access()->assertWithinDailyLimit($user);
    }

    public function test_the_storage_quota_blocks_a_user_who_is_over_cap(): void
    {
        $this->setSetting('max_storage_mb_per_user', 1, 'integer');
        $user = $this->user();

        AipAsset::create([
            'user_id' => $user->id,
            'source' => 'generated',
            'disk' => 'public',
            'path' => 'ai-image-pro/x.png',
            'bytes' => 2 * 1024 * 1024, // 2 MB against a 1 MB cap
        ]);

        $this->expectException(HttpResponseException::class);
        $this->access()->assertWithinStorageQuota($user);
    }

    public function test_a_zero_storage_cap_means_unlimited(): void
    {
        $this->setSetting('max_storage_mb_per_user', 0, 'integer');
        $user = $this->user();

        AipAsset::create([
            'user_id' => $user->id,
            'source' => 'generated',
            'disk' => 'public',
            'path' => 'ai-image-pro/x.png',
            'bytes' => 500 * 1024 * 1024,
        ]);

        $this->access()->assertWithinStorageQuota($user);
        $this->addToAssertionCount(1);
    }

    public function test_retention_uses_the_free_window_for_a_non_paying_user(): void
    {
        $this->setSetting('retention_days_free', 7, 'integer');
        $this->setSetting('retention_days_paid', 0, 'integer');

        $expires = $this->access()->expiresAtFor($this->user());

        $this->assertNotNull($expires);
        $this->assertEqualsWithDelta(7, now()->diffInDays($expires, false), 1);
    }

    public function test_a_zero_retention_window_keeps_images_forever(): void
    {
        $this->setSetting('retention_days_free', 0, 'integer');
        $this->setSetting('retention_days_paid', 0, 'integer');

        $this->assertNull($this->access()->expiresAtFor($this->user()));
    }

    public function test_watermarking_is_off_unless_the_admin_enables_it_with_text(): void
    {
        $user = $this->user();

        $this->setSetting('watermark_enabled', false, 'boolean');
        $this->setSetting('watermark_text', 'MakeAI');
        $this->assertFalse($this->access()->shouldWatermark($user));

        // Enabled but with no text configured — nothing to stamp.
        $this->setSetting('watermark_enabled', true, 'boolean');
        $this->setSetting('watermark_text', '');
        $this->assertFalse($this->access()->shouldWatermark($user));

        $this->setSetting('watermark_text', 'MakeAI');
        $this->assertTrue($this->access()->shouldWatermark($user));
    }

    public function test_the_studio_is_unreachable_while_the_addon_is_disabled(): void
    {
        $this->setSetting('enabled', false, 'boolean');

        $this->assertFalse($this->access()->canAccessStudio($this->user()));
    }

    // ─── License mode: quota (Regular) vs metered (Extended) ─────
    //
    // credit_quota_mode() === ! isProAvailable(), so "quota mode" means there are no
    // subscriptions, no plans and no premium tier. Every plan/paid-scoped behaviour in
    // this addon must collapse accordingly — User::isPro() alone does NOT know about the
    // license, so pairing it with isProAvailable() is what keeps these honest.

    private function useQuotaMode(): void
    {
        settings_set('license_type', '1', 'integer', 'license');
        settings_set('subscriptions_enabled', '0', 'boolean', 'ai');
        Setting::flushCache();
    }

    private function useMeteredMode(): void
    {
        settings_set('license_type', '2', 'integer', 'license');
        settings_set('subscriptions_enabled', '1', 'boolean', 'ai');
        Setting::flushCache();
    }

    /** A user carrying a paid plan — the shape that used to leak past the license gate. */
    private function planUser(string $slug = 'pro'): User
    {
        $plan = \App\Models\Plan::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'price_monthly' => 50,
            'price_yearly' => 500,
            'vat_percentage' => 0,
            'credits' => 1000,
            'is_active' => true,
            'is_free' => false,
            'sort_order' => 1,
        ]);

        return $this->user([
            'plan_id' => $plan->id,
            'subscription_ends_at' => now()->addYear(),
        ]);
    }

    public function test_quota_mode_offers_no_premium_or_plan_access_levels(): void
    {
        $this->useQuotaMode();

        $levels = array_column(app(AccessLevelService::class)->getOptions(), 'value');

        $this->assertNotContains('premium', $levels);
        $this->assertEmpty(array_filter($levels, fn ($l) => str_starts_with((string) $l, 'plan:')));

        // ...so an op can never end up gated behind a tier that cannot exist: a stored
        // `premium` override is refused and the shipped default stands.
        $this->setOperations(['upscale' => ['access' => 'premium']]);
        $this->assertSame('login', $this->registry()->accessLevel('upscale'));
    }

    public function test_metered_mode_does_offer_premium_and_plan_levels(): void
    {
        $this->useMeteredMode();
        $this->planUser('gold');
        app(AccessLevelService::class)->clearCache();

        $levels = array_column(app(AccessLevelService::class)->getOptions(), 'value');

        $this->assertContains('premium', $levels);
        $this->assertContains('plan:gold', $levels);

        // And now the override the quota-mode test refused is honoured.
        $this->setOperations(['upscale' => ['access' => 'premium']]);
        $this->assertSame('premium', $this->registry()->accessLevel('upscale'));
    }

    public function test_quota_mode_ignores_per_plan_daily_limits(): void
    {
        $user = $this->planUser('pro');

        $this->setSetting('user_daily_limit', 10, 'integer');
        $this->setSetting('plan_daily_limits', ['pro' => 500], 'json');

        // Metered: the plan override applies.
        $this->useMeteredMode();
        $this->assertSame(500, $this->access()->dailyLimitFor($user->fresh()));

        // Quota: plans cannot apply to anyone, so everyone falls to the logged-in limit.
        $this->useQuotaMode();
        $this->assertSame(10, $this->access()->dailyLimitFor($user->fresh()));
    }

    public function test_quota_mode_watermarks_even_a_user_holding_a_plan(): void
    {
        $user = $this->planUser();

        $this->setSetting('watermark_enabled', true, 'boolean');
        $this->setSetting('watermark_text', 'MakeAI');

        // Metered: a paid user is exempt.
        $this->useMeteredMode();
        $this->assertFalse($this->access()->shouldWatermark($user->fresh()));

        // Quota: "paid" does not exist, so the mark applies to everyone — a stale
        // plan_id must not silently buy an exemption.
        $this->useQuotaMode();
        $this->assertTrue($this->access()->shouldWatermark($user->fresh()));
    }

    public function test_quota_mode_uses_the_free_retention_window_even_for_a_plan_holder(): void
    {
        $user = $this->planUser();

        $this->setSetting('retention_days_free', 3, 'integer');
        $this->setSetting('retention_days_paid', 90, 'integer');

        $this->useMeteredMode();
        $this->assertSame(90, $this->access()->retentionDaysFor($user->fresh()));

        $this->useQuotaMode();
        $this->assertSame(3, $this->access()->retentionDaysFor($user->fresh()));
    }

    public function test_retention_has_a_separate_window_for_guests_free_and_premium(): void
    {
        $this->useMeteredMode();

        $this->setSetting('retention_days_guest', 1, 'integer');
        $this->setSetting('retention_days_free', 30, 'integer');
        $this->setSetting('retention_days_paid', 365, 'integer');

        $access = $this->access();

        // Three distinct buckets, decided only by who made the image.
        $this->assertSame(1, $access->retentionDaysFor(null));                  // guest
        $this->assertSame(30, $access->retentionDaysFor($this->user()));        // signed in, free
        $this->assertSame(365, $access->retentionDaysFor($this->planUser()));   // signed in, paid

        // 0 on any bucket means keep forever — expiresAtFor stamps no deadline.
        $this->setSetting('retention_days_guest', 0, 'integer');
        $this->assertNull($this->access()->expiresAtFor(null));
    }

    public function test_a_watermark_logo_replaces_the_text_and_stands_alone(): void
    {
        $user = $this->user();
        $this->useMeteredMode();

        $this->setSetting('watermark_enabled', true, 'boolean');
        $this->setSetting('watermark_text', '');
        $this->setSetting('watermark_logo_path', '');
        $this->assertFalse($this->access()->shouldWatermark($user));

        // A logo alone is enough — no text required.
        $this->setSetting('watermark_logo_path', 'ai-image-pro/watermark/logo.png');
        $this->assertTrue($this->access()->shouldWatermark($user));
    }
}
