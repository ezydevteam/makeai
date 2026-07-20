<?php

namespace Tests\Feature;

use App\Http\Middleware\LicenseMiddleware;
use App\Http\Middleware\ThrottleAiRequests;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Installer\Middleware\InstallationMiddleware;
use Tests\TestCase;

/**
 * The "Opt-in Preferences" and "Cookie Preferences" feature toggles hide their cards
 * on the user privacy page AND make the server ignore those values, so a stale form
 * cannot write preferences the site no longer offers.
 */
class PrivacyPreferenceTogglesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.require_verified' => false]);
        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class, ThrottleAiRequests::class]);
        Setting::flushCache();
    }

    private function user(): User
    {
        return User::factory()->create(['email_marketing' => false, 'allow_data_improve' => false]);
    }

    public function test_both_sections_are_enabled_by_default(): void
    {
        $props = $this->actingAs($this->user())
            ->get(route('user.dashboard.privacy'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertTrue($props['optinPreferencesEnabled']);
        $this->assertTrue($props['cookiePreferencesEnabled']);
    }

    public function test_disabling_the_toggles_hides_both_sections(): void
    {
        settings_set('optin_preferences_enabled', false, 'boolean', 'features');
        settings_set('cookie_preferences_enabled', false, 'boolean', 'features');
        Setting::flushCache();

        $props = $this->actingAs($this->user())
            ->get(route('user.dashboard.privacy'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertFalse($props['optinPreferencesEnabled']);
        $this->assertFalse($props['cookiePreferencesEnabled']);
    }

    public function test_optin_values_are_ignored_while_disabled(): void
    {
        settings_set('optin_preferences_enabled', false, 'boolean', 'features');
        Setting::flushCache();
        $user = $this->user();

        $this->actingAs($user)->post(route('user.dashboard.privacy.preferences'), [
            'email_marketing' => true,
            'allow_data_improve' => true,
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertFalse((bool) $user->email_marketing);
        $this->assertFalse((bool) $user->allow_data_improve);
    }

    public function test_cookie_consent_is_ignored_while_disabled(): void
    {
        settings_set('cookie_preferences_enabled', false, 'boolean', 'features');
        Setting::flushCache();
        $user = $this->user();

        $this->actingAs($user)->post(route('user.dashboard.privacy.preferences'), [
            'email_marketing' => true,
            'allow_data_improve' => true,
            'cookie_consent' => ['analytics' => true],
        ])->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->cookie_consent);
        // The opt-ins are a separate section and must still save.
        $this->assertTrue((bool) $user->fresh()->email_marketing);
    }

    public function test_preferences_save_normally_when_enabled(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('user.dashboard.privacy.preferences'), [
            'email_marketing' => true,
            'allow_data_improve' => true,
            'cookie_consent' => ['analytics' => true],
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue((bool) $user->email_marketing);
        $this->assertSame(['analytics' => true], $user->cookie_consent);
    }
}
