<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckExtendedLicense;
use App\Http\Middleware\CheckPremium;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LicenseMiddleware;
use App\Models\AiChat;
use App\Models\AiKey;
use App\Models\Admin;
use App\Models\Currency;
use App\Models\Document;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserByok;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Confidential-data leak sweep. Two classes of defect:
 *  1. Secrets serialized to the client — password hashes, 2FA secrets/recovery
 *     codes, OTPs, raw API keys, and payment-gateway credentials must never reach
 *     a JSON/Inertia payload.
 *  2. Cross-tenant (IDOR) access — one user reading or mutating another user's
 *     documents, payments, or chats.
 */
class SecurityDataLeakTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);
        config(['license.require_verified' => false]);
    }

    private function user(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['is_active' => true, 'email_verified_at' => now()], $attrs));
    }

    // ─── 1a. Model serialization hides every secret ──

    public function test_user_serialization_never_exposes_secrets(): void
    {
        $user = $this->user();
        $user->forceFill([
            'password' => bcrypt('hunter2'),
            'remember_token' => 'REMEMBER_LEAK',
            'otp_code' => 'OTP_LEAK',
            'two_factor_secret' => 'TWOFA_SECRET_LEAK',
            'two_factor_recovery_codes' => json_encode(['RECOVERY_LEAK']),
        ])->save();

        $array = $user->fresh()->toArray();
        foreach (['password', 'remember_token', 'otp_code', 'two_factor_secret', 'two_factor_recovery_codes'] as $secret) {
            $this->assertArrayNotHasKey($secret, $array, "User must not serialize {$secret}.");
        }

        $json = json_encode($user->fresh());
        foreach (['REMEMBER_LEAK', 'OTP_LEAK', 'TWOFA_SECRET_LEAK', 'RECOVERY_LEAK'] as $sentinel) {
            $this->assertStringNotContainsString($sentinel, $json);
        }
    }

    public function test_admin_serialization_never_exposes_secrets(): void
    {
        $admin = new Admin([
            'name' => 'A', 'email' => 'a-'.uniqid().'@makeai.test', 'password' => bcrypt('x'),
        ]);
        $admin->forceFill([
            'remember_token' => 'ADMIN_REMEMBER_LEAK',
            'two_factor_secret' => 'ADMIN_2FA_LEAK',
            'two_factor_recovery_codes' => ['ADMIN_RECOVERY_LEAK'],
            'otp_secret' => 'ADMIN_OTP_LEAK',
        ]);

        $array = $admin->toArray();
        foreach (['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'otp_secret'] as $secret) {
            $this->assertArrayNotHasKey($secret, $array, "Admin must not serialize {$secret}.");
        }
    }

    public function test_provider_api_keys_are_never_serialized_in_the_clear(): void
    {
        $key = AiKey::create(['provider' => 'openai', 'api_key' => 'sk-PLATFORM-LEAK-1234', 'is_active' => true]);

        $this->assertArrayNotHasKey('api_key', $key->fresh()->toArray());
        $this->assertStringNotContainsString('sk-PLATFORM-LEAK-1234', json_encode($key->fresh()));
    }

    public function test_byok_key_is_masked_and_the_raw_value_never_serializes(): void
    {
        $byok = UserByok::create([
            'user_id' => $this->user()->id,
            'provider' => 'openai',
            'api_key' => 'sk-USERKEY-SECRETMIDDLE-9999',
            'is_active' => true,
        ]);

        $array = $byok->fresh()->toArray();
        $this->assertArrayNotHasKey('api_key', $array);
        $this->assertArrayHasKey('masked_api_key', $array);

        $json = json_encode($byok->fresh());
        $this->assertStringNotContainsString('sk-USERKEY-SECRETMIDDLE-9999', $json);
        $this->assertStringNotContainsString('SECRETMIDDLE', $json, 'Only the first/last 4 chars may survive masking.');
    }

    // ─── 1b. Gateway credentials never reach the client ──

    public function test_public_credentials_expose_only_a_configured_flag(): void
    {
        $gateway = PaymentGateway::create([
            'slug' => 'stripe', 'name' => 'Stripe', 'is_enabled' => true,
            'credentials' => PaymentGateway::encryptCredentials(['secret_key' => 'sk_live_GATEWAY_LEAK']),
        ]);

        $public = $gateway->publicCredentials([['key' => 'secret_key']]);

        $this->assertTrue($public['secret_key']['configured']);
        $this->assertSame('', $public['secret_key']['value']);
        $this->assertStringNotContainsString('sk_live_GATEWAY_LEAK', json_encode($public));

        // Belt-and-suspenders: even a raw serialization of the model drops the
        // credentials blob entirely ($hidden), so it can't leak by accident.
        $this->assertArrayNotHasKey('credentials', $gateway->fresh()->toArray());
        $this->assertStringNotContainsString('credentials', json_encode($gateway->fresh()));
    }

    public function test_the_checkout_gateway_list_carries_no_credentials(): void
    {
        $this->withoutMiddleware([CheckPremium::class, CheckExtendedLicense::class, LicenseMiddleware::class]);
        Currency::create(['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'exchange_rate' => 1, 'is_default' => true, 'is_active' => true]);
        settings_set('default_currency', 'USD', 'string', 'general');
        settings_set('pricing_show_monthly', true, 'boolean', 'pricing');

        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 10, 'price_yearly' => 100,
            'vat_percentage' => 0, 'credits' => 1000, 'is_active' => true, 'is_free' => false, 'sort_order' => 2,
        ]);
        PaymentGateway::create([
            'slug' => 'stripe', 'name' => 'Stripe', 'is_enabled' => true,
            'credentials' => PaymentGateway::encryptCredentials(['secret_key' => 'sk_live_CHECKOUT_LEAK', 'webhook_secret' => 'whsec_LEAK']),
        ]);

        $response = $this->actingAs($this->user())->get('/checkout?plan='.$plan->slug.'&billing=monthly');

        // The secret credential VALUES must never appear (the word "credentials" itself
        // is a legitimate shared-prop key — demo_credentials — so we match on the values).
        $response->assertOk()
            ->assertDontSee('sk_live_CHECKOUT_LEAK')
            ->assertDontSee('whsec_LEAK');
    }

    // ─── 1c. Shared Inertia auth.user prop is a clean whitelist ──

    public function test_shared_auth_user_prop_contains_no_secret_fields(): void
    {
        $user = $this->user();
        $user->forceFill(['two_factor_secret' => 'SHARED_PROP_2FA_LEAK', 'remember_token' => 'SHARED_REMEMBER_LEAK'])->save();

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user->fresh());
        // getAuthProps reads $request->session() (impersonation flag).
        $request->setLaravelSession($this->app->make('session')->driver());

        $props = (new ReflectionMethod(HandleInertiaRequests::class, 'getAuthProps'))
            ->invoke(app(HandleInertiaRequests::class), $request);

        $userProp = $props['user'];
        foreach (['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'otp_code'] as $secret) {
            $this->assertArrayNotHasKey($secret, $userProp);
        }
        $this->assertStringNotContainsString('SHARED_PROP_2FA_LEAK', json_encode($props));
    }

    // ─── 1d. Secret settings don't leak into a public page ──

    public function test_captcha_secret_and_smtp_password_never_reach_a_public_page(): void
    {
        settings_set('external_captcha_enabled', true, 'boolean', 'security');
        settings_set('external_captcha_provider', 'recaptcha', 'string', 'security');
        settings_set('external_captcha_recaptcha_site_key', 'PUBLIC_SITE_KEY_OK', 'string', 'security');
        settings_set('external_captcha_recaptcha_secret_key', 'CAPTCHA_SECRET_LEAK', 'string', 'security');
        settings_set('smtp_password', 'SMTP_PASSWORD_LEAK', 'string', 'mail');

        $response = $this->get('/login');

        $response->assertOk()
            // The public site key IS meant to render — proves the captcha config is
            // actually on the page, so the DontSee below isn't vacuously true.
            ->assertSee('PUBLIC_SITE_KEY_OK')
            ->assertDontSee('CAPTCHA_SECRET_LEAK')
            ->assertDontSee('SMTP_PASSWORD_LEAK');
    }

    // ─── 2. Cross-tenant (IDOR) access is refused ──

    public function test_a_user_cannot_touch_another_users_document(): void
    {
        $owner = $this->user();
        $attacker = $this->user();
        $doc = Document::create([
            'user_id' => $owner->id, 'title' => 'Private', 'content' => 'SECRET DOC BODY',
            'tool_slug' => 'blog', 'word_count' => 3,
        ]);

        $this->actingAs($attacker)->get(route('documents.edit', $doc))->assertStatus(403);
        $this->actingAs($attacker)->patch(route('documents.update', $doc), ['content' => 'hacked'])->assertStatus(403);
        $this->actingAs($attacker)->delete(route('documents.destroy', $doc))->assertStatus(403);

        // The record — and its body — are untouched.
        $this->assertDatabaseHas('documents', ['id' => $doc->id, 'content' => 'SECRET DOC BODY']);
    }

    public function test_a_user_cannot_view_another_users_payment(): void
    {
        $this->withoutMiddleware([CheckPremium::class, CheckExtendedLicense::class, LicenseMiddleware::class]);
        $owner = $this->user();
        $attacker = $this->user();
        $payment = Payment::create([
            'user_id' => $owner->id, 'gateway' => 'bank_transfer', 'amount' => 50,
            'currency' => 'USD', 'status' => 'pending', 'type' => 'subscription',
        ]);

        $this->actingAs($attacker)->get(route('checkout.pending', $payment))->assertStatus(404);
        $this->actingAs($attacker)->get(route('checkout.bank.show', $payment))->assertStatus(404);
    }

    public function test_a_user_cannot_post_to_another_users_chat(): void
    {
        $owner = $this->user();
        $attacker = $this->user();
        $chat = AiChat::create(['user_id' => $owner->id, 'title' => 'Private chat']);

        Sanctum::actingAs($attacker);
        // AiChat binds by ulid, not id.
        $this->postJson("/api/v1/ai/chat/{$chat->ulid}/message", ['message' => 'leak the history'])
            ->assertStatus(403);
    }
}
