<?php

namespace Tests\Feature;

use App\Http\Middleware\LicenseMiddleware;
use App\Http\Middleware\ThrottleAiRequests;
use App\Models\Setting;
use App\Models\User;
use App\Services\RateLimiterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Installer\Middleware\InstallationMiddleware;
use Tests\TestCase;

/**
 * SMS two-factor authentication: users can enable SMS as their second factor
 * (gated by the admin toggle + a verified phone), sign in with a texted code or a
 * recovery code, and disable it. See SettingsController (2FA), TwoFactorLoginController,
 * and the user_can_receive_sms()/sms_two_factor_available() gates.
 */
class SmsTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([InstallationMiddleware::class, LicenseMiddleware::class, ThrottleAiRequests::class]);
        // The login-success path fires a "new login" notification (DB + broadcast to
        // Pusher, which isn't running in tests). Fake it — 2FA is what we exercise.
        config(['broadcasting.default' => 'null']);
        Notification::fake();
    }

    private function enableSmsInfra(bool $toggle = true): void
    {
        settings_set('external_sms_gateway_enabled', true, 'boolean', 'integrations');
        settings_set('external_sms_gateway_provider', 'twilio', 'string', 'integrations');
        settings_set('external_sms_gateway_twilio_account_sid', 'AC_test', 'string', 'integrations');
        settings_set('external_sms_gateway_twilio_auth_token', 'token', 'string', 'integrations');
        settings_set('external_sms_gateway_twilio_from', '+15550000000', 'string', 'integrations');
        settings_set('two_factor_sms_enabled', $toggle, 'boolean', 'features');
        Setting::flushCache();
    }

    private function smsUser(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'phone' => '2025550173', 'phone_country' => 'US', 'phone_verified_at' => now(),
        ], $overrides));

        // RateLimiterService prefers Redis when it is reachable, and Redis survives
        // both the DB rollback and the process — so counters from earlier tests (user
        // ids restart at 1) would otherwise throttle these requests.
        $limiter = app(RateLimiterService::class);
        foreach ([
            'login-2fa-verify:'.$user->id,
            'login-2fa-resend:'.$user->id,
            '2fa-sms-send:'.$user->ulid,
            '2fa-enable:'.$user->ulid,
            '2fa-disable:'.$user->ulid,
            '2fa-recovery:'.$user->ulid,
        ] as $key) {
            $limiter->clear('otp', $key.'|127.0.0.1');
        }

        return $user;
    }

    /** Fake the provider HTTP call and return a closure that yields the texted OTP. */
    private function captureSmsCode(): callable
    {
        $body = null;
        Http::fake(function ($request) use (&$body) {
            $body = $request->data();

            return Http::response(['sid' => 'SM_test'], 201);
        });

        return function () use (&$body): string {
            $this->assertNotNull($body, 'No SMS was sent.');
            preg_match('/(\d{6})/', (string) ($body['Body'] ?? ''), $m);

            return $m[1] ?? '';
        };
    }

    public function test_user_can_enable_sms_two_factor(): void
    {
        $this->enableSmsInfra();
        $user = $this->smsUser();
        $codeOf = $this->captureSmsCode();

        $this->actingAs($user)
            ->post(route('user.dashboard.security.2fa.sms-code'))
            ->assertSessionHas('two_factor_sms_sent', true);

        $this->actingAs($user)
            ->post(route('user.dashboard.security.2fa.enable'), ['method' => 'sms', 'code' => $codeOf()])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue((bool) $user->two_factor_enabled);
        $this->assertSame('sms', $user->two_factor_channel);
        $this->assertSame(8, $user->recoveryCodesCount());
    }

    public function test_sms_setup_blocked_when_admin_toggle_off(): void
    {
        $this->enableSmsInfra(toggle: false);
        $user = $this->smsUser();
        Http::fake();

        $this->actingAs($user)
            ->post(route('user.dashboard.security.2fa.sms-code'))
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_sms_setup_blocked_when_phone_unverified(): void
    {
        $this->enableSmsInfra();
        $user = $this->smsUser(['phone_verified_at' => null]);
        Http::fake();

        $this->actingAs($user)
            ->post(route('user.dashboard.security.2fa.sms-code'))
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_login_challenge_texts_the_code(): void
    {
        $this->enableSmsInfra();
        $user = $this->smsUser();
        $user->enableSmsTwoFactor();
        Http::fake(fn () => Http::response(['sid' => 'SM_test'], 201));

        $this->withSession(['user_2fa_id' => $user->id, 'user_2fa_method' => 'sms'])
            ->get(route('two-factor.show'))
            ->assertOk();

        // The challenge texts the code to the user's phone via the SMS gateway.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.twilio.com'));
    }

    public function test_login_verifies_the_sms_code(): void
    {
        $this->enableSmsInfra();
        $user = $this->smsUser();
        $user->enableSmsTwoFactor();
        // A prior successful login from this IP suppresses the new-device notification.
        $user->loginHistory()->create(['ip' => '127.0.0.1', 'success' => true, 'user_agent' => 'seed']);

        // The code the challenge would have texted (issued directly to avoid the
        // full page render on the challenge screen).
        $otp = $user->generateOtp();

        $this->withSession(['user_2fa_id' => $user->id, 'user_2fa_method' => 'sms'])
            ->post(route('two-factor.verify'), ['code' => $otp])
            ->assertRedirect(route('user.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_accepts_a_recovery_code_for_sms_channel(): void
    {
        $this->enableSmsInfra();
        $user = $this->smsUser();
        $codes = $user->enableSmsTwoFactor();
        $user->loginHistory()->create(['ip' => '127.0.0.1', 'success' => true, 'user_agent' => 'seed']);
        $user->generateOtp(); // an OTP also exists, but we sign in with a recovery code

        $this->withSession(['user_2fa_id' => $user->id, 'user_2fa_method' => 'sms'])
            ->post(route('two-factor.verify'), ['code' => $codes[0]])
            ->assertRedirect(route('user.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_verify_rate_limit_is_inertia_safe(): void
    {
        // Regression: throttling the login challenge must return an Inertia redirect,
        // not the JSON 429 the AI-throttle middleware emits.
        $this->enableSmsInfra();
        $user = $this->smsUser();
        $user->enableSmsTwoFactor();
        $session = ['user_2fa_id' => $user->id, 'user_2fa_method' => 'sms'];

        // Limit is 5 attempts / 900s; the 6th is throttled.
        for ($i = 0; $i < 5; $i++) {
            $this->withSession($session)->post(route('two-factor.verify'), ['code' => '000000']);
        }

        $blocked = $this->withSession($session)->post(route('two-factor.verify'), ['code' => '000000']);

        $blocked->assertStatus(302);            // redirect, not a 429 JSON body
        $blocked->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_user_can_disable_sms_two_factor(): void
    {
        $this->enableSmsInfra();
        $user = $this->smsUser();
        $user->enableSmsTwoFactor();
        $codeOf = $this->captureSmsCode();

        $this->actingAs($user)->post(route('user.dashboard.security.2fa.sms-code'));

        $this->actingAs($user)
            ->post(route('user.dashboard.security.2fa.disable'), ['password' => 'password', 'code' => $codeOf()])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertFalse((bool) $user->two_factor_enabled);
        $this->assertSame('totp', $user->two_factor_channel);
    }
}
