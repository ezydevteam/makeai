<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\AddonLicense;
use App\Models\Admin;
use App\Services\AddonLicenseService;
use App\Services\AddonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AddonLicenseTest extends TestCase
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

            // Bind the public key in app container for AddonLicenseService
            app()->instance('test.license_public_key', $this->testPublicKeyBase64);
        } else {
            $this->markTestSkipped('Sodium extension is required for signature tests.');
        }

        // Mock addon configuration
        $mockAddonService = $this->mock(AddonService::class);
        $mockAddonService->shouldReceive('getAddonConfig')
            ->with('ai-assistant')
            ->andReturn([
                'slug' => 'ai-assistant',
                'name' => 'AI Assistant',
                'version' => '1.0.0',
                'requires_license' => 1,
            ]);

        $mockAddonService->shouldReceive('deactivate')
            ->byDefault()
            ->andReturn(true);
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

    public function test_blocks_first_time_activation_without_a_verified_license(): void
    {
        // AddonService activate blocks if requires_license is set but not licensed
        $addonService = new AddonService();
        
        $manifest = [
            'slug' => 'ai-assistant',
            'requires_license' => 1,
        ];

        // Ensure database row exists
        Addon::create([
            'slug' => 'ai-assistant',
            'name' => 'AI Assistant',
            'version' => '1.0.0',
            'is_active' => false,
            'manifest' => $manifest,
        ]);

        $this->assertFalse($addonService->activate('ai-assistant'));
    }

    public function test_rejects_a_purchase_code_with_invalid_uuid_format_without_calling_the_license_server(): void
    {
        Http::fake();

        $service = app(AddonLicenseService::class);
        $result = $service->verify('ai-assistant', 'invalid-code-format');

        $this->assertFalse($result->valid);
        $this->assertEquals('invalid_format', $result->errorCode);
        Http::assertNothingSent();
    }

    public function test_rejects_a_response_with_an_invalid_or_missing_ed25519_signature(): void
    {
        $payload = [
            'valid' => true,
            'slug' => 'ai-assistant',
            'item_id' => 51234567,
            'license_type' => 1,
            'buyer' => 'john_doe',
            'purchased_at' => '2026-05-01T10:00:00Z',
            'supported_until' => '2026-11-01T10:00:00Z',
            'verified_at' => '2026-06-12T08:00:00Z',
            'error' => null,
        ];

        // Mock response with invalid signature
        $mockResponseBody = $this->generateSignedResponse($payload, false);

        Http::fake([
            'https://license.ezydev.net/api/v1/verify' => Http::response($mockResponseBody),
        ]);

        $service = app(AddonLicenseService::class);
        $result = $service->verify('ai-assistant', '12345678-1234-1234-1234-123456789012');

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

        $service = app(AddonLicenseService::class);
        $result = $service->verify('ai-assistant', '12345678-1234-1234-1234-123456789012');

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

        $service = app(AddonLicenseService::class);
        $result = $service->verify('ai-assistant', '12345678-1234-1234-1234-123456789012');

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

        $service = app(AddonLicenseService::class);
        $result = $service->verify('ai-assistant', '12345678-1234-1234-1234-123456789012');

        $this->assertFalse($result->valid);
        $this->assertEquals('revoked', $result->errorCode);
    }

    public function test_stores_the_purchase_code_encrypted_and_never_returns_it_in_any_response(): void
    {
        $payload = [
            'valid' => true,
            'slug' => 'ai-assistant',
            'item_id' => 51234567,
            'license_type' => 1,
            'buyer' => 'john_doe',
            'purchased_at' => '2026-05-01T10:00:00Z',
            'supported_until' => '2026-11-01T10:00:00Z',
            'verified_at' => '2026-06-12T08:00:00Z',
            'error' => null,
        ];

        $purchaseCode = '12345678-1234-1234-1234-123456789012';
        $mockResponseBody = $this->generateSignedResponse($payload);

        Http::fake([
            'https://license.ezydev.net/api/v1/verify' => Http::response($mockResponseBody),
        ]);

        $service = app(AddonLicenseService::class);
        $service->verify('ai-assistant', $purchaseCode);

        $license = AddonLicense::where('addon_slug', 'ai-assistant')->first();
        $this->assertNotNull($license);
        $this->assertNotEquals($purchaseCode, $license->purchase_code);
        $this->assertEquals($purchaseCode, Crypt::decryptString($license->purchase_code));

        $info = $service->getLicenseInfo('ai-assistant');
        $this->assertStringContainsString('xxxx', $info['purchase_code_masked']);
        $this->assertStringNotContainsString($purchaseCode, $info['purchase_code_masked']);
    }

    public function test_activates_the_addon_after_successful_signed_verification(): void
    {
        $payload = [
            'valid' => true,
            'slug' => 'ai-assistant',
            'item_id' => 51234567,
            'license_type' => 1,
            'buyer' => 'john_doe',
            'purchased_at' => '2026-05-01T10:00:00Z',
            'supported_until' => '2026-11-01T10:00:00Z',
            'verified_at' => '2026-06-12T08:00:00Z',
            'error' => null,
        ];

        $mockResponseBody = $this->generateSignedResponse($payload);

        Http::fake([
            'https://license.ezydev.net/api/v1/verify' => Http::response($mockResponseBody),
        ]);

        $service = app(AddonLicenseService::class);
        $result = $service->verify('ai-assistant', '12345678-1234-1234-1234-123456789012');

        $this->assertTrue($result->valid);
        $this->assertTrue($service->isLicensed('ai-assistant'));
    }

    public function test_skips_the_license_modal_on_reactivation_when_a_valid_license_exists(): void
    {
        AddonLicense::create([
            'addon_slug' => 'ai-assistant',
            'purchase_code' => Crypt::encryptString('12345678-1234-1234-1234-123456789012'),
            'envato_item_id' => 51234567,
            'license_type' => 1,
            'buyer' => 'john_doe',
            'domain' => 'localhost',
            'verified_at' => now(),
            'status' => 'valid',
        ]);

        $service = app(AddonLicenseService::class);
        $this->assertTrue($service->isLicensed('ai-assistant'));
    }

    public function test_rate_limits_verification_attempts_to_5_per_minute(): void
    {
        // Disable core license verification requirement for the test
        config(['license.require_verified' => false]);

        // Fake the HTTP request so it returns quickly and doesn't hit a real server
        Http::fake([
            'https://license.ezydev.net/*' => Http::response(['error' => 'invalid_code'], 422),
        ]);

        // Create a role first to satisfy foreign key constraints
        $role = \App\Models\AdminRole::create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'is_system' => true,
        ]);

        config(['auth.providers.admins.super_admin_slug' => 'super-admin']);

        $admin = Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $admin->load('role');
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue($admin->hasPermission('addons.view'));
        $this->actingAs($admin, 'admin');

        // Force throttle key trigger
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/admin/appearance/addons/ai-assistant/verify-license', [
                'purchase_code' => '12345678-1234-1234-1234-123456789012',
            ]);
        }

        $response = $this->postJson('/admin/appearance/addons/ai-assistant/verify-license', [
            'purchase_code' => '12345678-1234-1234-1234-123456789012',
        ]);

        $response->assertStatus(429);
    }

    public function test_marks_license_as_grace_only_on_signed_invalidation_and_deactivates_after_72h(): void
    {
        Mail::fake();

        // 1. Create a valid license
        $license = AddonLicense::create([
            'addon_slug' => 'ai-assistant',
            'purchase_code' => Crypt::encryptString('12345678-1234-1234-1234-123456789012'),
            'envato_item_id' => 51234567,
            'license_type' => 1,
            'buyer' => 'john_doe',
            'domain' => 'localhost',
            'verified_at' => now()->subDays(8),
            'status' => 'valid',
        ]);

        // Mock signed invalidation response
        $payload = [
            'valid' => false,
            'error' => 'revoked',
        ];
        $mockResponseBody = $this->generateSignedResponse($payload);

        Http::fake([
            'https://license.ezydev.net/api/v1/verify' => Http::response($mockResponseBody),
        ]);

        $service = app(AddonLicenseService::class);
        
        // Run reverify to trigger grace status
        $service->reverify('ai-assistant');

        $license->refresh();
        $this->assertEquals('grace', $license->status);
        $this->assertNotNull($license->grace_started_at);

        // Adjust grace start to 73 hours ago
        $license->update([
            'grace_started_at' => now()->subHours(73),
        ]);

        // Run reverify again to trigger invalidation
        $service->reverify('ai-assistant');

        $license->refresh();
        $this->assertEquals('invalid', $license->status);
    }

    public function test_does_not_punish_network_failures_or_license_server_downtime_during_reverification(): void
    {
        $license = AddonLicense::create([
            'addon_slug' => 'ai-assistant',
            'purchase_code' => Crypt::encryptString('12345678-1234-1234-1234-123456789012'),
            'envato_item_id' => 51234567,
            'license_type' => 1,
            'buyer' => 'john_doe',
            'domain' => 'localhost',
            'verified_at' => now()->subDays(8),
            'status' => 'valid',
        ]);

        // Mock network timeout failure by throwing connection exception in fake callback
        Http::fake(function ($request) {
            throw new \Illuminate\Http\Client\ConnectionException('Timeout');
        });

        $service = app(AddonLicenseService::class);
        $service->reverify('ai-assistant');

        $license->refresh();
        $this->assertEquals('valid', $license->status);
        $this->assertNull($license->grace_started_at);
    }

    public function test_blocks_verification_in_demo_mode(): void
    {
        config(['demo.enabled' => true]);

        $service = app(AddonLicenseService::class);
        $result = $service->verify('ai-assistant', '12345678-1234-1234-1234-123456789012');

        $this->assertFalse($result->valid);
        $this->assertEquals('demo_mode', $result->errorCode);
    }
}
