<?php

namespace Tests\Feature;

use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gateway credentials must not carry surrounding whitespace.
 *
 * A key pasted with a trailing newline is invisible in the admin form but goes straight
 * into an Authorization header, and the gateway then rejects the *header* rather than the
 * key — Paddle answers "Authentication header included, but incorrectly formatted", which
 * sends you looking at the request-building code instead of at the pasted value.
 */
class GatewayCredentialTrimTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_credential_is_trimmed_before_it_is_stored(): void
    {
        $gateway = PaymentGateway::create([
            'slug' => 'paddle', 'name' => 'Paddle', 'is_enabled' => true,
            'credentials' => PaymentGateway::encryptCredentials([
                'api_key' => "  pdl_sdbx_apikey_01test\n",
            ]),
        ]);

        $this->assertSame('pdl_sdbx_apikey_01test', $gateway->getCredential('api_key'));
    }

    /** Already-stored values are repaired on read, with no re-paste and no migration. */
    public function test_a_credential_stored_untrimmed_is_trimmed_on_read(): void
    {
        $gateway = PaymentGateway::create([
            'slug' => 'razorpay', 'name' => 'Razorpay', 'is_enabled' => true,
            // Bypasses encryptCredentials() to mimic a value saved before the fix.
            'credentials' => ['key_id' => \Illuminate\Support\Facades\Crypt::encryptString("rzp_test_abc \t")],
        ]);

        $this->assertSame('rzp_test_abc', $gateway->getCredential('key_id'));
    }

    /** Internal newlines must survive — PEM keys and bank instructions depend on them. */
    public function test_internal_newlines_are_preserved(): void
    {
        $pem = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBg\n-----END PUBLIC KEY-----";

        $gateway = PaymentGateway::create([
            'slug' => 'paddle', 'name' => 'Paddle', 'is_enabled' => true,
            'credentials' => PaymentGateway::encryptCredentials(['public_key' => "\n".$pem."\n\n"]),
        ]);

        $this->assertSame($pem, $gateway->getCredential('public_key'));
    }

    /** A whitespace-only value is nothing, not a configured credential. */
    public function test_a_whitespace_only_credential_is_treated_as_unset(): void
    {
        $encrypted = PaymentGateway::encryptCredentials(['api_key' => "   \n"]);

        $this->assertSame([], $encrypted);
    }
}
