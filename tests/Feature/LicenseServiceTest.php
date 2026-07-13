<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LicenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $testPublicKeyBase64;
    private string $testSecretKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Generate dynamic keypair for testing signature verification
        if (function_exists('sodium_crypto_sign_keypair')) {
            $keypair = sodium_crypto_sign_keypair();
            $publicKey = sodium_crypto_sign_publickey($keypair);
            $this->testSecretKey = sodium_crypto_sign_secretkey($keypair);
            $this->testPublicKeyBase64 = base64_encode($publicKey);

            // Bind the public key in app container for LicenseService
            app()->instance('test.license_public_key', $this->testPublicKeyBase64);
        } else {
            $this->markTestSkipped('Sodium extension is required for signature tests.');
        }

        // Run AdminSeeder so that admin roles (role_id = 1) exist to satisfy FK constraints
        (new \Database\Seeders\AdminSeeder())->run();

        // Ensure settings are clear
        Cache::flush();
    }

    private function generateSignedResponse(array $payload, bool $validSignature = true): array
    {
        $payloadJson = json_encode($payload);
        $signature = '';

        if ($validSignature) {
            $signatureBytes = sodium_crypto_sign_detached($payloadJson, $this->testSecretKey);
            $signature = base64_encode($signatureBytes);
        } else {
            // Ed25519 signatures must be exactly 64 bytes long
            $signature = base64_encode(str_repeat('x', 64));
        }

        return [
            'payload' => $payload,
            'signature' => $signature,
        ];
    }

    public function test_rejects_a_purchase_code_with_invalid_uuid_format_without_calling_the_license_server(): void
    {
        Http::fake();

        $service = app(LicenseService::class);
        $result = $service->verify('invalid-code-format');

        $this->assertFalse($result->valid);
        $this->assertEquals('invalid_format', $result->errorCode);
        Http::assertNothingSent();
    }

    public function test_rejects_a_response_with_an_invalid_or_missing_ed25519_signature(): void
    {
        $payload = [
            'valid' => true,
            'license_type' => 1,
            'buyer' => 'john_doe',
            'purchased_at' => '2026-05-01T10:00:00Z',
            'supported_until' => '2026-11-01T10:00:00Z',
            'error' => null,
        ];

        // Mock response with invalid signature
        $mockResponseBody = $this->generateSignedResponse($payload, false);

        Http::fake([
            'https://license.ezydev.net/api/v1/verify' => Http::response($mockResponseBody),
        ]);

        $service = app(LicenseService::class);
        $result = $service->verify('12345678-1234-1234-1234-123456789012');

        $this->assertFalse($result->valid);
        $this->assertEquals('signature_invalid', $result->errorCode);
    }

    public function test_rejects_a_signed_wrong_item_response(): void
    {
        $payload = [
            'valid' => false,
            'error' => 'wrong_item',
        ];

        $mockResponseBody = $this->generateSignedResponse($payload);

        Http::fake([
            'https://license.ezydev.net/api/v1/verify' => Http::response($mockResponseBody),
        ]);

        $service = app(LicenseService::class);
        $result = $service->verify('12345678-1234-1234-1234-123456789012');

        $this->assertFalse($result->valid);
        $this->assertEquals('wrong_item', $result->errorCode);
    }

    public function test_rejects_a_signed_refunded_response(): void
    {
        $payload = [
            'valid' => false,
            'error' => 'refunded',
        ];

        $mockResponseBody = $this->generateSignedResponse($payload);

        Http::fake([
            'https://license.ezydev.net/api/v1/verify' => Http::response($mockResponseBody),
        ]);

        $service = app(LicenseService::class);
        $result = $service->verify('12345678-1234-1234-1234-123456789012');

        $this->assertFalse($result->valid);
        $this->assertEquals('refunded', $result->errorCode);
    }

    public function test_rejects_a_signed_revoked_response(): void
    {
        $payload = [
            'valid' => false,
            'error' => 'revoked',
        ];

        $mockResponseBody = $this->generateSignedResponse($payload);

        Http::fake([
            'https://license.ezydev.net/api/v1/verify' => Http::response($mockResponseBody),
        ]);

        $service = app(LicenseService::class);
        $result = $service->verify('12345678-1234-1234-1234-123456789012');

        $this->assertFalse($result->valid);
        $this->assertEquals('revoked', $result->errorCode);
    }

    public function test_stores_all_license_fields_encrypted_in_settings_and_never_returns_the_code_in_any_response(): void
    {
        $purchaseCode = '12345678-1234-1234-1234-123456789012';
        $payload = [
            'valid' => true,
            'license_type' => 1,
            'buyer' => 'john_doe',
            'purchased_at' => '2026-05-01T10:00:00Z',
            'supported_until' => '2026-11-01T10:00:00Z',
            'error' => null,
        ];

        $mockResponseBody = $this->generateSignedResponse($payload);

        Http::fake([
            'https://license.ezydev.net/api/v1/verify' => Http::response($mockResponseBody),
        ]);

        $service = app(LicenseService::class);
        $result = $service->verify($purchaseCode);

        $this->assertTrue($result->valid);

        // Verify settings storage
        $storedCodeEnc = settings('license_purchase_code');
        $this->assertNotEmpty($storedCodeEnc);
        $this->assertNotEquals($purchaseCode, $storedCodeEnc);
        $this->assertEquals($purchaseCode, Crypt::decryptString($storedCodeEnc));

        $this->assertEquals(1, settings('license_type'));
        $this->assertEquals('john_doe', settings('license_buyer'));
        $this->assertEquals('valid', settings('license_status'));

        // Check getStatus does not expose code
        $status = $service->getStatus();
        $this->assertArrayNotHasKey('purchase_code', $status);
        $this->assertArrayNotHasKey('license_purchase_code', $status);
    }

    public function test_activates_with_license_type_1_on_regular_license(): void
    {
        $payload = [
            'valid' => true,
            'license_type' => 1,
            'buyer' => 'john_doe',
            'purchased_at' => '2026-05-01T10:00:00Z',
            'supported_until' => '2026-11-01T10:00:00Z',
            'error' => null,
        ];

        $mockResponseBody = $this->generateSignedResponse($payload);

        Http::fake([
            'https://license.ezydev.net/api/v1/verify' => Http::response($mockResponseBody),
        ]);

        $service = app(LicenseService::class);
        $result = $service->verify('12345678-1234-1234-1234-123456789012');

        $this->assertTrue($result->valid);
        $this->assertEquals(1, settings('license_type'));
        $this->assertEquals(1, $service->getLicenseType());
        $this->assertFalse($service->isExtended());
    }

    public function test_activates_with_license_type_2_on_extended_license(): void
    {
        $payload = [
            'valid' => true,
            'license_type' => 2,
            'buyer' => 'john_doe',
            'purchased_at' => '2026-05-01T10:00:00Z',
            'supported_until' => '2026-11-01T10:00:00Z',
            'error' => null,
        ];

        $mockResponseBody = $this->generateSignedResponse($payload);

        Http::fake([
            'https://license.ezydev.net/api/v1/verify' => Http::response($mockResponseBody),
        ]);

        $service = app(LicenseService::class);
        $result = $service->verify('12345678-1234-1234-1234-123456789012');

        $this->assertTrue($result->valid);
        $this->assertEquals(2, settings('license_type'));
        $this->assertEquals(2, $service->getLicenseType());
        $this->assertTrue($service->isExtended());
    }

    public function test_is_pro_available_returns_true_only_when_extended_and_subscriptions_enabled(): void
    {
        // 1. Extended = false, Subscriptions = false
        settings_set('license_type', 1, 'integer', 'license');
        settings_set('subscriptions_enabled', false, 'boolean', 'license');
        $this->assertFalse(isProAvailable());

        // 2. Extended = true, Subscriptions = false
        settings_set('license_type', 2, 'integer', 'license');
        settings_set('subscriptions_enabled', false, 'boolean', 'license');
        $this->assertFalse(isProAvailable());

        // 3. Extended = false, Subscriptions = true
        settings_set('license_type', 1, 'integer', 'license');
        settings_set('subscriptions_enabled', true, 'boolean', 'license');
        $this->assertFalse(isProAvailable());

        // 4. Extended = true, Subscriptions = true
        settings_set('license_type', 2, 'integer', 'license');
        settings_set('subscriptions_enabled', true, 'boolean', 'license');
        $this->assertTrue(isProAvailable());
    }

    public function test_reverify_keeps_status_on_network_failure_and_never_starts_grace(): void
    {
        // Setup valid license
        settings_set('license_purchase_code', Crypt::encryptString('12345678-1234-1234-1234-123456789012'), 'encrypted', 'license');
        settings_set('license_status', 'valid', 'string', 'license');
        settings_set('license_verified_at', now()->subDays(10)->toDateTimeString(), 'string', 'license');

        Http::fake([
            'https://license.ezydev.net/*' => Http::response(null, 500),
        ]);

        $service = app(LicenseService::class);
        $result = $service->reverify(true); // force

        $this->assertFalse($result);
        $this->assertEquals('valid', settings('license_status'));
        $this->assertNull(settings('license_grace_started_at'));
    }

    public function test_reverify_starts_72h_grace_only_on_a_signed_invalid_response(): void
    {
        // Setup valid license
        settings_set('license_purchase_code', Crypt::encryptString('12345678-1234-1234-1234-123456789012'), 'encrypted', 'license');
        settings_set('license_status', 'valid', 'string', 'license');
        settings_set('license_verified_at', now()->subDays(10)->toDateTimeString(), 'string', 'license');

        $payload = [
            'valid' => false,
            'error' => 'revoked',
        ];
        $mockResponseBody = $this->generateSignedResponse($payload);

        Http::fake([
            'https://license.ezydev.net/api/v1/verify' => Http::response($mockResponseBody),
        ]);

        $service = app(LicenseService::class);
        $result = $service->reverify(true); // force

        $this->assertFalse($result);
        $this->assertEquals('grace', settings('license_status'));
        $this->assertNotNull(settings('license_grace_started_at'));
    }

    public function test_marks_license_invalid_after_72h_grace_and_stops_frontend_features(): void
    {
        Mail::fake();
        \Illuminate\Support\Facades\Notification::fake();

        // Setup license in grace period (> 72 hours ago)
        settings_set('license_purchase_code', Crypt::encryptString('12345678-1234-1234-1234-123456789012'), 'encrypted', 'license');
        settings_set('license_status', 'grace', 'string', 'license');
        settings_set('license_grace_started_at', now()->subHours(73)->toDateTimeString(), 'string', 'license');
        settings_set('subscriptions_enabled', true, 'boolean', 'license');

        // Create active admin
        Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin-test@makeai.com',
            'password' => bcrypt('password'),
            'role_id' => 1,
            'is_active' => true,
        ]);

        $payload = [
            'valid' => false,
            'error' => 'revoked',
        ];
        $mockResponseBody = $this->generateSignedResponse($payload);

        Http::fake([
            'https://license.ezydev.net/api/v1/verify' => Http::response($mockResponseBody),
        ]);

        $service = app(LicenseService::class);
        $result = $service->reverify(true); // force

        $this->assertFalse($result);
        $this->assertEquals('invalid', settings('license_status'));
        $this->assertFalse((bool) settings('subscriptions_enabled'));
        $this->assertFalse(license_verified());

        // Verify database log exists
        $this->assertDatabaseHas('mail_logs', [
            'template_slug' => 'core_license_deactivated',
            'recipient_email' => 'admin-test@makeai.com',
        ]);
    }

    public function test_license_middleware_blocks_admin_routes_when_license_is_invalid(): void
    {
        settings_set('license_status', 'invalid', 'string', 'license');
        settings_set('license_grace_started_at', null, 'string', 'license');
        config(['license.require_verified' => true]);

        $admin = Admin::create([
            'name' => 'Admin User',
            'email' => 'admin-test-2@example.com',
            'password' => bcrypt('password'),
            'role_id' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/dashboard');

        $response->assertRedirect(route('admin.license.settings'));
    }

    public function test_install_wizard_step_4_checks_license_without_writing_to_database(): void
    {
        // The installer is gated by InstallationMiddleware, which 404s /install/*
        // once the app is installed. The local .env has INSTALLED=true, so force it
        // off for this test to exercise the wizard.
        config(['app.installed' => false]);

        $payload = [
            'valid' => true,
            'license_type' => 1,
            'buyer' => 'john_doe',
            'purchased_at' => '2026-05-01T10:00:00Z',
            'supported_until' => '2026-11-01T10:00:00Z',
            'error' => null,
        ];
        $mockResponseBody = $this->generateSignedResponse($payload);

        Http::fake([
            'https://license.ezydev.net/api/v1/verify' => Http::response($mockResponseBody),
        ]);

        $sessionData = [
            'current_step' => 4,
            'steps_completed' => [1, 2, 3],
            'data' => [
                'step_1' => ['confirmed' => true],
                'step_2' => [
                    'db_host' => '127.0.0.1',
                    'db_port' => 3306,
                    'db_database' => 'test_db',
                    'db_username' => 'root',
                    'db_password' => '',
                ],
                'step_3' => [
                    'site_name' => 'Test Site',
                    'site_url' => 'http://localhost',
                ]
            ]
        ];

        $response = $this->withSession(['install_wizard' => $sessionData])
            ->post('/install/step/4', [
                'purchase_code' => '12345678-1234-1234-1234-123456789012',
            ]);

        $response->assertRedirect('/install');

        $updatedSession = session('install_wizard');
        $this->assertNotNull($updatedSession['data']['step_4']['license_result']);
        $this->assertEquals(1, $updatedSession['data']['step_4']['license_result']['type']);

        // Settings table should not contain the license_purchase_code key
        $this->assertNull(settings('license_purchase_code'));
    }

    public function test_manual_activation_via_controller_succeeds_with_valid_license(): void
    {
        $role = \App\Models\AdminRole::where('slug', 'super-admin')->first()
            ?? \App\Models\AdminRole::create([
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'is_system' => true,
            ]);

        config(['auth.providers.admins.super_admin_slug' => 'super-admin']);

        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin-controller-test@makeai.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $payload = [
            'valid' => true,
            'license_type' => 1,
            'buyer' => 'john_doe',
            'purchased_at' => '2026-05-01T10:00:00Z',
            'supported_until' => '2026-11-01T10:00:00Z',
            'error' => null,
        ];

        $mockResponseBody = $this->generateSignedResponse($payload);

        Http::fake([
            'https://license.ezydev.net/api/v1/verify' => Http::response($mockResponseBody),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post('/admin/license/activate', [
                'purchase_code' => '12345678-1234-1234-1234-123456789012',
            ]);

        $response->assertRedirect(route('admin.license.settings'));
        $this->assertEquals('valid', settings('license_status'));
    }
}

