<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * The admin gateway screen's credential handling.
 *
 * Two rules hold this together, and breaking either one bricks the screen:
 *
 *   1. A blank credential field means "keep what is stored". Treating blank as "missing"
 *      locked the Enable toggle forever once a gateway was configured, because stored
 *      secrets are never sent to the browser so every saved field renders blank.
 *   2. A gateway may not be enabled while a credential it declares is genuinely absent.
 *      Enforced server-side — the Vue toggle is a courtesy, not a control.
 */
class GatewayCredentialUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.testing.ensure_pages_exist' => false]);

        // LicenseMiddleware blocks every state-changing request until the licence is
        // activated, so without this each POST below tests the licence gate instead.
        settings_set('license_type', '2', 'integer', 'license');
        $this->withoutMiddleware(\App\Http\Middleware\LicenseMiddleware::class);
    }

    /** @return array<string, mixed> the gateway's Inertia props */
    private function gatewayProps(string $slug): array
    {
        $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.payment-gateways.index'));
        $response->assertOk();

        $gateways = $response->getOriginalContent()->getData()['page']['props']['gateways'];

        return collect($gateways)->firstWhere('slug', $slug);
    }

    private function admin(): Admin
    {
        $role = AdminRole::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);

        // firstOrCreate, not create: several tests here hit the screen twice.
        return Admin::firstOrCreate(
            ['email' => 'root@example.com'],
            ['name' => 'Root', 'password' => 'password', 'role_id' => $role->id, 'is_active' => true],
        );
    }

    private function paystack(array $credentials = ['public_key' => 'pk_test_abcdef123456', 'secret_key' => 'sk_test_abcdef123456']): PaymentGateway
    {
        return PaymentGateway::create([
            'slug' => 'paystack', 'name' => 'Paystack', 'is_enabled' => false, 'is_test_mode' => true,
            'credentials' => PaymentGateway::encryptCredentials($credentials),
        ]);
    }

    private function save(PaymentGateway $gateway, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin(), 'admin')->post(
            route('admin.payment-gateways.update', $gateway->id),
            array_merge([
                'is_enabled' => true,
                'is_test_mode' => true,
                'processing_fee_type' => 'none',
                'processing_fee_value' => 0,
                'credentials' => [],
            ], $overrides),
        );
    }

    // ─── Masking ────────────────────────────────

    public function test_a_stored_credential_is_exposed_only_as_a_mask(): void
    {
        $this->paystack();

        $secret = $this->gatewayProps('paystack')['credentials']['secret_key'];

        $this->assertTrue($secret['configured']);
        $this->assertSame('sk_••••••456', $secret['masked']);

        // The plaintext must not appear anywhere in the payload.
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.payment-gateways.index'))
            ->assertDontSee('sk_test_abcdef123456', false);
    }

    /** A short secret would be mostly revealed by a partial mask, so reveal nothing. */
    public function test_a_short_credential_reveals_no_characters(): void
    {
        $this->paystack(['secret_key' => 'sk_12']);

        $this->assertSame('•••••', $this->gatewayProps('paystack')['credentials']['secret_key']['masked']);
    }

    /** Nothing saved means no mask to show. */
    public function test_an_unset_credential_has_an_empty_mask(): void
    {
        $this->paystack(['secret_key' => 'sk_test_abcdef123456']);

        $publicKey = $this->gatewayProps('paystack')['credentials']['public_key'];

        $this->assertFalse($publicKey['configured']);
        $this->assertSame('', $publicKey['masked']);
    }

    /**
     * A credential encrypted under a since-changed APP_KEY is configured but unusable.
     * It must not present as a working credential — the UI turns this into "Re-enter".
     */
    public function test_an_undecryptable_credential_is_configured_but_unmasked(): void
    {
        PaymentGateway::create([
            'slug' => 'paystack', 'name' => 'Paystack', 'is_enabled' => false,
            'credentials' => ['secret_key' => 'not-actually-encrypted'],
        ]);

        $secret = $this->gatewayProps('paystack')['credentials']['secret_key'];

        $this->assertTrue($secret['configured']);
        $this->assertSame('', $secret['masked']);
    }

    // ─── Blank means "keep" ─────────────────────

    /**
     * The bug this whole change is about: enabling an already-configured gateway without
     * retyping its secrets. Blank fields must neither block the save nor wipe the values.
     */
    public function test_a_configured_gateway_can_be_enabled_without_retyping_its_secrets(): void
    {
        $gateway = $this->paystack();

        $this->save($gateway)->assertSessionHasNoErrors();

        $gateway->refresh();
        $this->assertTrue($gateway->is_enabled);
        $this->assertSame('sk_test_abcdef123456', $gateway->getCredential('secret_key'));
        $this->assertSame('pk_test_abcdef123456', $gateway->getCredential('public_key'));
    }

    /** And it can be turned back off again, which the lock also prevented. */
    public function test_a_configured_gateway_can_be_disabled_again(): void
    {
        $gateway = $this->paystack();
        $gateway->update(['is_enabled' => true]);

        $this->save($gateway, ['is_enabled' => false])->assertSessionHasNoErrors();

        $this->assertFalse($gateway->fresh()->is_enabled);
    }

    public function test_a_submitted_credential_replaces_the_stored_one(): void
    {
        $gateway = $this->paystack();

        $this->save($gateway, ['credentials' => ['secret_key' => 'sk_test_replaced99']]);

        $gateway->refresh();
        $this->assertSame('sk_test_replaced99', $gateway->getCredential('secret_key'));
        // Untouched field kept its value rather than being blanked.
        $this->assertSame('pk_test_abcdef123456', $gateway->getCredential('public_key'));
    }

    // ─── Server-side enable gate ────────────────

    /** A Vue toggle is not a control. An enabled gateway missing a key fails at checkout. */
    public function test_enabling_is_refused_when_a_credential_is_genuinely_absent(): void
    {
        $gateway = $this->paystack(['public_key' => 'pk_test_abcdef123456']);

        $this->save($gateway)->assertSessionHasErrors('credentials.secret_key');

        $this->assertFalse($gateway->fresh()->is_enabled);
    }

    /** Supplying the missing one in the same request is enough — no second save needed. */
    public function test_enabling_succeeds_when_the_missing_credential_is_supplied_now(): void
    {
        $gateway = $this->paystack(['public_key' => 'pk_test_abcdef123456']);

        $this->save($gateway, ['credentials' => ['secret_key' => 'sk_test_brandnew12']])
            ->assertSessionHasNoErrors();

        $this->assertTrue($gateway->fresh()->is_enabled);
    }

    /** Saving a DISABLED gateway with nothing configured is fine — it sells nothing. */
    public function test_a_disabled_gateway_may_be_saved_with_no_credentials(): void
    {
        $gateway = PaymentGateway::create([
            'slug' => 'paystack', 'name' => 'Paystack', 'is_enabled' => false, 'credentials' => [],
        ]);

        $this->save($gateway, ['is_enabled' => false])->assertSessionHasNoErrors();
    }

    /** An unusable credential blocks enabling just as an absent one does. */
    public function test_enabling_is_refused_when_a_credential_will_not_decrypt(): void
    {
        $gateway = PaymentGateway::create([
            'slug' => 'paystack', 'name' => 'Paystack', 'is_enabled' => false,
            'credentials' => [
                'public_key' => Crypt::encryptString('pk_test_abcdef123456'),
                'secret_key' => 'encrypted-under-an-old-app-key',
            ],
        ]);

        $this->save($gateway)->assertSessionHasErrors('credentials.secret_key');

        $this->assertFalse($gateway->fresh()->is_enabled);
    }
}
