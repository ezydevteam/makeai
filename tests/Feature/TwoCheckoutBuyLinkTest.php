<?php

namespace Tests\Feature;

use App\Http\Controllers\CheckoutController;
use App\Http\Middleware\CheckPremium;
use App\Http\Middleware\LicenseMiddleware;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2Checkout (Verifone) ConvertPlus buy links.
 *
 * The link this app built was rejected outright — clicking "proceed to checkout" landed
 * the buyer on 2Checkout's documentation site rather than a payment form. Three causes,
 * each sufficient on its own:
 *
 *   1. No `signature`. A dynamic (ad-hoc price) link MUST be signed; without it the price
 *      in the URL would be freely editable, so 2Checkout refuses the link.
 *   2. No `type` parameter, which is required for dynamic products.
 *   3. A `sandbox.2checkout.com` host that does not serve checkouts. Both environments run
 *      on secure.2checkout.com; test mode is the `test=1` flag.
 */
class TwoCheckoutBuyLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        settings_set('license_type', '2', 'integer', 'license');
        config(['broadcasting.default' => 'null']);
        $this->withoutMiddleware([CheckPremium::class, LicenseMiddleware::class]);
    }

    private function signaturePayload(array $params): string
    {
        $method = new \ReflectionMethod(CheckoutController::class, 'twoCheckoutSignaturePayload');

        return $method->invoke(app(CheckoutController::class), $params);
    }

    /**
     * Pinned to 2Checkout's own published worked example. If the serialisation drifts —
     * wrong sort, character count instead of byte count, a separator — every buy link
     * silently stops working, and the only symptom is a redirect to their docs.
     */
    public function test_the_signature_payload_matches_the_documented_example(): void
    {
        $payload = $this->signaturePayload([
            'currency' => 'USD',
            'expiration' => '1893456000',
            'price' => '10',
            'prod' => 'Software',
            'qty' => '1',
            'type' => 'digital',
        ]);

        $this->assertSame('3USD1018934560002108Software117digital', $payload);
    }

    /** Order of the input array must not matter — the algorithm sorts by name. */
    public function test_the_signature_payload_is_order_independent(): void
    {
        $ordered = ['currency' => 'USD', 'price' => '10', 'prod' => 'Software', 'qty' => '1', 'type' => 'digital'];
        $shuffled = ['type' => 'digital', 'prod' => 'Software', 'qty' => '1', 'currency' => 'USD', 'price' => '10'];

        $this->assertSame($this->signaturePayload($ordered), $this->signaturePayload($shuffled));
    }

    /** The prefix counts BYTES — a plan name with an accent must not be counted as chars. */
    public function test_the_signature_payload_counts_bytes_not_characters(): void
    {
        // "Café" is 4 characters but 5 bytes in UTF-8.
        $this->assertSame('5Café', $this->signaturePayload(['prod' => 'Café']));
    }

    private function checkout(bool $testMode, string $planName = 'Pro'): string
    {
        PaymentGateway::create([
            'slug' => '2checkout', 'name' => '2Checkout', 'is_enabled' => true, 'is_test_mode' => $testMode,
            'credentials' => PaymentGateway::encryptCredentials([
                'merchant_code' => '256123245650', 'secret_key' => 'secret-word',
            ]),
        ]);

        $plan = Plan::create([
            'name' => $planName, 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 50, 'price_yearly' => 500,
            'vat_percentage' => 0, 'credits' => 1000,
            'is_active' => true, 'is_free' => false, 'sort_order' => 2,
        ]);

        $this->actingAs(User::factory()->create(['is_active' => true, 'email_verified_at' => now()]));

        return $this->post(route('checkout.session'), [
            'plan' => $plan->slug, 'billing' => 'monthly', 'gateway' => '2checkout',
        ])->headers->get('Location');
    }

    private function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    private function expectedSignature(string $returnUrl, string $ulid, string $price = '50.00'): string
    {
        return hash_hmac('sha256', $this->signaturePayload([
            'currency' => 'USD',
            'order-ext-ref' => $ulid,
            'price' => $price,
            'prod' => 'Pro',
            'qty' => '1',
            'return-type' => 'redirect',
            'return-url' => $returnUrl,
            'type' => 'digital',
        ]), 'secret-word');
    }

    /**
     * The signature must cover the GENERAL signed params (return-url, return-type,
     * order-ext-ref) as well as the dynamic-product ones. Signing only the product params
     * leaves the link rejected exactly as an unsigned one is.
     */
    public function test_the_buy_link_signs_the_general_params_as_well_as_the_product_ones(): void
    {
        $url = $this->checkout(testMode: false);
        $query = $this->queryOf($url);

        $this->assertSame('digital', $query['type'] ?? null, 'type is required for dynamic products.');
        $this->assertSame(64, strlen((string) ($query['signature'] ?? '')), 'signature must be a SHA-256 hex digest.');

        $this->assertSame(
            $this->expectedSignature($query['return-url'], $query['order-ext-ref']),
            $query['signature'],
        );
    }

    /**
     * `order-ext-ref` is the ConvertPlus name; the legacy `merchant_order_id` is not a
     * ConvertPlus parameter. It returns as INS `vendor_order_id`, which is the only thing
     * tying a paid order back to a user — get the name wrong and the money arrives
     * unattributable.
     */
    public function test_the_buy_link_carries_the_reference_the_webhook_matches_on(): void
    {
        $query = $this->queryOf($this->checkout(testMode: false));

        $payment = \App\Models\Payment::where('gateway', '2checkout')->firstOrFail();

        $this->assertSame($payment->ulid, $query['order-ext-ref'] ?? null);
        $this->assertArrayNotHasKey('merchant_order_id', $query);
        $this->assertArrayNotHasKey('x_receipt_link_url', $query);
    }

    /** The whole point of signing: an edited price no longer matches. */
    public function test_an_edited_price_invalidates_the_signature(): void
    {
        $query = $this->queryOf($this->checkout(testMode: false));

        $this->assertNotSame(
            $this->expectedSignature($query['return-url'], $query['order-ext-ref'], price: '1.00'),
            $query['signature'],
        );
    }

    /** A swapped return-url must invalidate it too, or the general params aren't covered. */
    public function test_an_edited_return_url_invalidates_the_signature(): void
    {
        $query = $this->queryOf($this->checkout(testMode: false));

        $this->assertNotSame(
            $this->expectedSignature('https://attacker.example/receipt', $query['order-ext-ref']),
            $query['signature'],
        );
    }

    /** Both environments live on secure.2checkout.com — the sandbox host serves no checkout. */
    public function test_test_mode_uses_the_live_host_with_a_test_flag(): void
    {
        $url = $this->checkout(testMode: true);

        $this->assertStringStartsWith('https://secure.2checkout.com/checkout/buy/', $url);
        $this->assertStringNotContainsString('sandbox.2checkout.com', $url);
        $this->assertSame('1', $this->queryOf($url)['test'] ?? null);
    }

    public function test_live_mode_sends_no_test_flag(): void
    {
        $query = $this->queryOf($this->checkout(testMode: false));

        $this->assertArrayNotHasKey('test', $query);
        $this->assertSame('256123245650', $query['merchant']);
        $this->assertSame('1', $query['dynamic']);
    }

    /** A signature needs the secret word, so an unsigned config must fail loudly. */
    public function test_a_missing_secret_word_fails_the_session(): void
    {
        PaymentGateway::create([
            'slug' => '2checkout', 'name' => '2Checkout', 'is_enabled' => true, 'is_test_mode' => false,
            'credentials' => PaymentGateway::encryptCredentials(['merchant_code' => '256123245650']),
        ]);

        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 50, 'price_yearly' => 500,
            'vat_percentage' => 0, 'credits' => 1000,
            'is_active' => true, 'is_free' => false, 'sort_order' => 2,
        ]);

        $this->actingAs(User::factory()->create(['is_active' => true, 'email_verified_at' => now()]));

        $this->post(route('checkout.session'), [
            'plan' => $plan->slug, 'billing' => 'monthly', 'gateway' => '2checkout',
        ])->assertSessionHas('error', fn ($error) => str_contains($error, '2Checkout is not configured'));

        $this->assertDatabaseHas('payments', ['gateway' => '2checkout', 'status' => 'failed']);
    }
}
