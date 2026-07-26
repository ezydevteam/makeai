<?php

namespace Tests\Feature;

use App\Http\Middleware\LicenseMiddleware;
use App\Http\Middleware\ThrottleAiRequests;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Installer\Middleware\InstallationMiddleware;
use Tests\TestCase;

/**
 * "Require Two-Factor Authentication" admin toggle: it forces users without 2FA to
 * the security page, and gates the SMS 2FA sub-option (which can only be on while
 * 2FA is required). See EnsureTwoFactorEnabled + FeatureSettingsController.
 */
class TwoFactorRequiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.require_verified' => false, 'broadcasting.default' => 'null']);
        // Settings live in the array cache, which leaks across test classes.
        Cache::flush();
        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class, ThrottleAiRequests::class]);
        Notification::fake();
    }

    private function admin(): Admin
    {
        $role = AdminRole::create(['name' => 'Super Admin', 'slug' => 'super-admin']);

        return Admin::create([
            'name' => 'Root', 'email' => 'root@example.com', 'password' => 'password',
            'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    /** Full valid feature-settings payload; override the bits under test. */
    private function featurePayload(array $overrides = []): array
    {
        $required = [
            'affiliate_enabled', 'tickets_enabled', 'contact_enabled', 'blog_enabled',
            'notifications_enabled', 'registration_enabled', 'email_verification_enabled',
            'onboarding_enabled', 'tools_review_approval_enabled', 'byok_enabled',
            'account_deletion_enabled', 'playground_enabled', 'chains_enabled',
            'tool_embeds_enabled', 'global_tools_brand_voice_enabled',
            'optin_preferences_enabled', 'cookie_preferences_enabled',
            'phone_required', 'two_factor_required', 'two_factor_sms_enabled',
        ];

        return array_merge(array_fill_keys($required, true), $overrides);
    }

    public function test_sms_2fa_is_independent_of_the_requirement(): void
    {
        // SMS can be an offered 2FA method without 2FA being mandatory.
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.features.settings.update'), $this->featurePayload([
                'two_factor_required' => false,
                'two_factor_sms_enabled' => true,
            ]))
            ->assertSessionHasNoErrors();

        Setting::flushCache();
        $this->assertFalse((bool) settings('two_factor_required'));
        $this->assertTrue((bool) settings('two_factor_sms_enabled'));
    }

    public function test_sms_2fa_persists_when_2fa_is_required(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.features.settings.update'), $this->featurePayload([
                'two_factor_required' => true,
                'two_factor_sms_enabled' => true,
            ]))
            ->assertSessionHasNoErrors();

        Setting::flushCache();
        $this->assertTrue((bool) settings('two_factor_required'));
        $this->assertTrue((bool) settings('two_factor_sms_enabled'));
    }

    public function test_user_without_2fa_is_redirected_when_required(): void
    {
        settings_set('two_factor_required', true, 'boolean', 'features');
        Setting::flushCache();
        $user = User::factory()->create(['two_factor_enabled' => false]);

        // The middleware short-circuits before the dashboard controller runs.
        $this->actingAs($user)
            ->get(route('user.dashboard'))
            ->assertRedirect(route('user.dashboard.security'));
    }

    public function test_security_page_is_reachable_while_locked_out(): void
    {
        settings_set('two_factor_required', true, 'boolean', 'features');
        Setting::flushCache();
        $user = User::factory()->create(['two_factor_enabled' => false]);

        $this->actingAs($user)
            ->get(route('user.dashboard.security'))
            ->assertOk();
    }

    public function test_other_authenticated_routes_are_gated_too(): void
    {
        // The gate is global (bootstrap/app.php), not scoped to the dashboard group,
        // so authenticated routes outside that group are covered as well.
        settings_set('two_factor_required', true, 'boolean', 'features');
        Setting::flushCache();
        $user = User::factory()->create(['two_factor_enabled' => false]);

        $this->actingAs($user)
            ->get(route('user.dashboard.byok'))
            ->assertRedirect(route('user.dashboard.security'));
    }

    public function test_public_product_pages_are_gated_for_signed_in_users(): void
    {
        // Core product surfaces (the AI tool pages) are public so guests can use them.
        // A signed-in user without 2FA must NOT be able to keep using them.
        settings_set('two_factor_required', true, 'boolean', 'features');
        Setting::flushCache();
        $user = User::factory()->create(['two_factor_enabled' => false]);

        $this->actingAs($user)
            ->get('/ai-tools')
            ->assertRedirect(route('user.dashboard.security'));
    }

    public function test_guests_are_never_gated(): void
    {
        // The requirement applies to signed-in users; guests browse normally.
        settings_set('two_factor_required', true, 'boolean', 'features');
        Setting::flushCache();

        $this->get('/ai-tools')->assertOk();
    }

    public function test_profile_stays_reachable_to_verify_a_phone(): void
    {
        // SMS 2FA needs a verified phone, so the profile page must stay reachable.
        settings_set('two_factor_required', true, 'boolean', 'features');
        Setting::flushCache();
        $user = User::factory()->create(['two_factor_enabled' => false]);

        $this->actingAs($user)
            ->get(route('user.dashboard.profile'))
            ->assertOk();
    }

    public function test_no_redirect_when_requirement_is_off(): void
    {
        // Default: not required → the middleware is a pass-through.
        $user = User::factory()->create(['two_factor_enabled' => false]);

        $this->actingAs($user)
            ->get(route('user.dashboard.security'))
            ->assertOk();
    }
}
