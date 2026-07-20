<?php

namespace Tests\Feature;

use App\Http\Middleware\LicenseMiddleware;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Installer\Middleware\InstallationMiddleware;
use Tests\TestCase;

/**
 * Phone verification via SMS OTP: the UI/flow is gated on an admin-configured
 * SMS gateway, the OTP is bound to the phone it was sent to, and any change to
 * phone/phone_country invalidates a prior verification. See SettingsController
 * (sendPhoneOtp/verifyPhoneOtp) and App\Services\SmsService.
 */
class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class]);

        $this->user = User::factory()->create([
            'phone' => '2025550173',
            'phone_country' => 'US',
            'phone_verified_at' => null,
        ]);
    }

    private function enableSmsGateway(): void
    {
        settings_set('external_sms_gateway_enabled', true, 'boolean', 'integrations');
        settings_set('external_sms_gateway_provider', 'twilio', 'string', 'integrations');
        settings_set('external_sms_gateway_twilio_account_sid', 'AC_test', 'string', 'integrations');
        settings_set('external_sms_gateway_twilio_auth_token', 'token_test', 'string', 'integrations');
        settings_set('external_sms_gateway_twilio_from', '+15550000000', 'string', 'integrations');
        Setting::flushCache();
    }

    /** Fake the provider HTTP call and capture the OTP from the SMS body. */
    private function fakeSmsCapturingCode(): callable
    {
        $body = null;
        Http::fake(function ($request) use (&$body) {
            $body = $request->data();

            return Http::response(['sid' => 'SM_test'], 201);
        });

        return function () use (&$body): string {
            $this->assertNotNull($body, 'No SMS was dispatched.');
            preg_match('/(\d{6})/', (string) ($body['Body'] ?? ''), $m);

            return $m[1] ?? '';
        };
    }

    public function test_send_otp_fails_when_gateway_disabled(): void
    {
        Http::fake();

        $this->actingAs($this->user)
            ->post(route('user.dashboard.profile.phone.send-otp'))
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertNull($this->user->fresh()->phone_verified_at);
    }

    public function test_full_send_and_verify_marks_phone_verified(): void
    {
        $this->enableSmsGateway();
        $codeOf = $this->fakeSmsCapturingCode();

        $this->actingAs($this->user)
            ->post(route('user.dashboard.profile.phone.send-otp'))
            ->assertSessionHas('phone_otp_sent', true)
            ->assertSessionHasNoErrors();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.twilio.com'));

        $this->actingAs($this->user)
            ->post(route('user.dashboard.profile.phone.verify-otp'), ['code' => $codeOf()])
            ->assertSessionHasNoErrors();

        $this->assertNotNull($this->user->fresh()->phone_verified_at);
    }

    public function test_failed_send_shows_clean_message_and_clears_otp(): void
    {
        // Twilio auth failure (bad/missing credentials). The raw error must not
        // reach the user — it would leak internals and be mis-sanitized on the
        // frontend into an "AI provider" message.
        $this->enableSmsGateway();
        Http::fake(fn () => Http::response(['message' => 'Authentication Error - invalid credentials'], 401));

        $response = $this->actingAs($this->user)
            ->post(route('user.dashboard.profile.phone.send-otp'));

        $response->assertRedirect();
        $error = session('error');
        $this->assertNotNull($error);
        $this->assertStringNotContainsStringIgnoringCase('authentication', $error);
        $this->assertStringNotContainsStringIgnoringCase('credentials', $error);

        // The pending OTP is rolled back so a failed send doesn't consume the slot.
        $this->assertNull($this->user->fresh()->otp_code);
    }

    public function test_wrong_code_is_rejected(): void
    {
        $this->enableSmsGateway();
        $codeOf = $this->fakeSmsCapturingCode();

        $this->actingAs($this->user)->post(route('user.dashboard.profile.phone.send-otp'));

        $wrong = $codeOf() === '000000' ? '111111' : '000000';

        $this->actingAs($this->user)
            ->post(route('user.dashboard.profile.phone.verify-otp'), ['code' => $wrong])
            ->assertSessionHasErrors('code');

        $this->assertNull($this->user->fresh()->phone_verified_at);
    }

    public function test_changing_phone_resets_verification(): void
    {
        $this->user->forceFill(['phone_verified_at' => now()])->save();

        $this->actingAs($this->user)
            ->put(route('user.dashboard.profile.update'), [
                'name' => 'Jane Doe',
                'email' => $this->user->email,
                'timezone' => 'UTC',
                'phone' => '2025550174', // different number
                'phone_country' => 'US',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->user->fresh()->phone_verified_at);
    }

    public function test_send_rate_limit_returns_inertia_redirect_not_json(): void
    {
        // Regression: throttling must not emit the JSON that the AI throttle
        // middleware returns — Inertia rejects a non-Inertia response.
        $this->enableSmsGateway();
        Http::fake(fn () => Http::response(['sid' => 'SM_test'], 201));

        // Limit is 4 sends/hour; the 5th must be throttled.
        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($this->user)->post(route('user.dashboard.profile.phone.send-otp'));
        }

        $blocked = $this->actingAs($this->user)->post(route('user.dashboard.profile.phone.send-otp'));

        $blocked->assertRedirect();          // 302, not a 429 JSON body
        $blocked->assertSessionHas('error');
    }

    public function test_verify_without_prior_send_asks_to_request_a_code(): void
    {
        $this->enableSmsGateway();

        $this->actingAs($this->user)
            ->post(route('user.dashboard.profile.phone.verify-otp'), ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->assertNull($this->user->fresh()->phone_verified_at);
    }

    public function test_otp_is_invalid_after_phone_changes(): void
    {
        $this->enableSmsGateway();
        $codeOf = $this->fakeSmsCapturingCode();

        // OTP issued against the original number.
        $this->actingAs($this->user)->post(route('user.dashboard.profile.phone.send-otp'));
        $code = $codeOf();

        // User changes the number before confirming.
        $this->actingAs($this->user)->put(route('user.dashboard.profile.update'), [
            'name' => 'Jane Doe',
            'email' => $this->user->email,
            'timezone' => 'UTC',
            'phone' => '2025550188',
            'phone_country' => 'US',
        ]);

        // The old code must not verify the new number.
        $this->actingAs($this->user)
            ->post(route('user.dashboard.profile.phone.verify-otp'), ['code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertNull($this->user->fresh()->phone_verified_at);
    }
}
