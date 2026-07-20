<?php

namespace Tests\Feature;

use App\Http\Middleware\LicenseMiddleware;
use App\Http\Middleware\ThrottleAiRequests;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Installer\Middleware\InstallationMiddleware;
use Tests\TestCase;

/**
 * "Require Phone Number": users are funnelled to their profile until a phone is on
 * file (verified when an SMS gateway is configured). Onboarding runs first, then
 * hands over to the gate. See EnsurePhoneProvided, phone_requirement_met() and
 * OnboardingController.
 */
class PhoneRequiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['license.require_verified' => false, 'broadcasting.default' => 'null']);
        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class, ThrottleAiRequests::class]);
        Notification::fake();

        settings_set('phone_required', true, 'boolean', 'features');
        // Onboarding off by default here so the gate applies immediately; the
        // onboarding interplay has its own tests below.
        settings_set('onboarding_enabled', false, 'boolean', 'features');
        Setting::flushCache();
    }

    private function enableSmsGateway(): void
    {
        settings_set('external_sms_gateway_enabled', true, 'boolean', 'integrations');
        settings_set('external_sms_gateway_provider', 'twilio', 'string', 'integrations');
        settings_set('external_sms_gateway_twilio_account_sid', 'AC_test', 'string', 'integrations');
        settings_set('external_sms_gateway_twilio_auth_token', 'token', 'string', 'integrations');
        settings_set('external_sms_gateway_twilio_from', '+15550000000', 'string', 'integrations');
        Setting::flushCache();
    }

    public function test_user_without_phone_is_sent_to_profile(): void
    {
        $user = User::factory()->create(['phone' => null, 'onboarding_completed_at' => now()]);

        $this->actingAs($user)
            ->get(route('user.dashboard'))
            ->assertRedirect(route('user.dashboard.profile'));
    }

    public function test_other_pages_are_blocked_too(): void
    {
        $user = User::factory()->create(['phone' => null, 'onboarding_completed_at' => now()]);

        // Public product surface — blocked for a signed-in user who owes a phone.
        $this->actingAs($user)
            ->get('/ai-tools')
            ->assertRedirect(route('user.dashboard.profile'));
    }

    public function test_profile_stays_reachable(): void
    {
        $user = User::factory()->create(['phone' => null, 'onboarding_completed_at' => now()]);

        $this->actingAs($user)
            ->get(route('user.dashboard.profile'))
            ->assertOk();
    }

    public function test_a_filled_phone_satisfies_the_rule_without_an_sms_gateway(): void
    {
        // No gateway configured → verification is impossible, so a number is enough.
        $user = User::factory()->create([
            'phone' => '2025550173', 'phone_country' => 'US', 'phone_verified_at' => null,
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($user)->get(route('user.dashboard'))->assertOk();
    }

    public function test_verification_is_required_once_a_gateway_is_configured(): void
    {
        $this->enableSmsGateway();
        $user = User::factory()->create([
            'phone' => '2025550173', 'phone_country' => 'US', 'phone_verified_at' => null,
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('user.dashboard'))
            ->assertRedirect(route('user.dashboard.profile'));

        $user->markPhoneAsVerified();

        $this->actingAs($user)->get(route('user.dashboard'))->assertOk();
    }

    public function test_onboarding_runs_before_the_gate(): void
    {
        settings_set('onboarding_enabled', true, 'boolean', 'features');
        Setting::flushCache();
        $user = User::factory()->create(['phone' => null, 'onboarding_completed_at' => null]);

        // Pending onboarding: the dashboard stays reachable so the welcome flow shows.
        $this->actingAs($user)->get(route('user.dashboard'))->assertOk();
    }

    public function test_completing_onboarding_redirects_to_profile(): void
    {
        settings_set('onboarding_enabled', true, 'boolean', 'features');
        Setting::flushCache();
        $user = User::factory()->create(['phone' => null, 'onboarding_completed_at' => null]);

        $this->actingAs($user)
            ->post(route('user.dashboard.onboarding.complete'), ['use_case' => 'marketing'])
            ->assertRedirect(route('user.dashboard.profile'));
    }

    public function test_skipping_onboarding_redirects_to_profile(): void
    {
        settings_set('onboarding_enabled', true, 'boolean', 'features');
        Setting::flushCache();
        $user = User::factory()->create(['phone' => null, 'onboarding_completed_at' => null]);

        $this->actingAs($user)
            ->post(route('user.dashboard.onboarding.skip'))
            ->assertRedirect(route('user.dashboard.profile'));
    }

    public function test_no_gating_when_the_requirement_is_off(): void
    {
        settings_set('phone_required', false, 'boolean', 'features');
        Setting::flushCache();
        $user = User::factory()->create(['phone' => null, 'onboarding_completed_at' => now()]);

        $this->actingAs($user)->get(route('user.dashboard'))->assertOk();
    }

    public function test_guests_are_never_gated(): void
    {
        $this->get('/ai-tools')->assertOk();
    }
}
