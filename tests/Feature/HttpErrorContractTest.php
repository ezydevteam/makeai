<?php

namespace Tests\Feature;

use App\Exceptions\AI\CreditLimitException;
use App\Exceptions\AI\InsufficientCreditsException;
use App\Exceptions\AI\IntegrationNotConfiguredException;
use App\Exceptions\StorageWriteException;
use App\Models\User;
use App\Services\RateLimiterService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The HTTP error contract for the JSON API and mutation endpoints: every failure
 * mode must return the right status code and a JSON body — never leak a 500, an
 * HTML login redirect, or a blank success. Covers 401 / 403 / 404 / 405 / 422 /
 * 429, plus the custom AI exception → status mappings (402 / 503 / 500).
 */
class HttpErrorContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);
        // Let API requests reach their controllers so we observe the real
        // auth/validation/not-found codes rather than a blanket license 403.
        config(['license.require_verified' => false]);

        $this->clearPublicThrottle();
    }

    /**
     * The 429 test trips the shared per-IP `public` rate limiter. When Redis is
     * available the counter lives there — which RefreshDatabase does NOT roll back —
     * so without an explicit clear it would bleed a 429 into unrelated tests (the
     * affiliate /ref and checkout coupon-preview tests also use `public`). Clear it
     * on both ends of every test in this class.
     */
    protected function clearPublicThrottle(): void
    {
        app(RateLimiterService::class)->clear('public', '127.0.0.1');
    }

    protected function tearDown(): void
    {
        $this->clearPublicThrottle();
        parent::tearDown();
    }

    private function user(): User
    {
        return User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
    }

    // ─── 401 Unauthenticated (JSON, not a login redirect) ──

    public function test_protected_api_endpoints_reject_guests_with_401(): void
    {
        $endpoints = [
            ['POST', '/api/v1/ai/complete'],       // auth:sanctum
            ['GET', '/api/v1/ai/chats'],           // auth:sanctum
            ['POST', '/api/v1/documents'],         // web + auth
            ['GET', '/api/v1/affiliate'],          // web + auth + affiliate
            ['POST', '/api/v1/affiliate/payouts'], // web + auth + affiliate
            ['POST', '/api/v1/tools/some-tool/reviews'], // auth:sanctum
        ];

        foreach ($endpoints as [$method, $uri]) {
            $this->json($method, $uri)->assertStatus(401);
        }
    }

    // ─── 404 Not Found ───────────────────────────

    public function test_an_unknown_api_route_returns_404_json(): void
    {
        $this->getJson('/api/v1/this-route-does-not-exist')
            ->assertStatus(404);
    }

    public function test_a_missing_model_binding_returns_404_not_500(): void
    {
        // The public reviews listing firstOrFail()s the tool — an unknown slug must
        // surface as a clean 404, not a ModelNotFound-driven 500.
        $this->getJson('/api/v1/tools/definitely-not-a-real-tool/reviews')
            ->assertStatus(404);
    }

    // ─── 405 Method Not Allowed (PUT / DELETE / PATCH) ──

    public function test_wrong_http_methods_return_405(): void
    {
        // /api/v1/tools is GET-only.
        $this->putJson('/api/v1/tools')->assertStatus(405);
        // /api/v1/documents is POST-only.
        $this->patchJson('/api/v1/documents')->assertStatus(405);
        // /api/v1/affiliate/payouts is GET/POST — DELETE is not allowed.
        $this->deleteJson('/api/v1/affiliate/payouts')->assertStatus(405);
    }

    // ─── 422 Validation ──────────────────────────

    public function test_a_post_with_an_invalid_body_returns_422_with_errors(): void
    {
        $this->actingAs($this->user())
            ->postJson('/api/v1/documents', [])   // missing required slug + content
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug', 'content']);
    }

    public function test_a_nonexistent_related_record_fails_validation_not_the_query(): void
    {
        // `slug` must exist in ai_tools — a bogus slug is a 422 (exists rule), never a
        // 500 from a downstream firstOrFail.
        $this->actingAs($this->user())
            ->postJson('/api/v1/documents', ['slug' => 'nope', 'content' => 'hello'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    // ─── 403 License invalid on the API ──────────

    public function test_the_api_returns_403_when_the_license_is_not_verified(): void
    {
        config(['license.require_verified' => true]);   // enforce for this test only

        $this->getJson('/api/v1/tools')
            ->assertStatus(403)
            ->assertJsonPath('message', 'LICENSE_INVALID');
    }

    // ─── 429 Too Many Requests ───────────────────

    public function test_a_throttled_route_returns_429_after_its_limit(): void
    {
        // /ref/{code} is public and limited to 30 requests/60s per IP. The 31st is
        // rejected with a JSON 429 carrying the RATE_LIMITED contract + Retry-After.
        //
        // The counter is primed directly rather than by issuing 31 real requests:
        // the non-redis limiter is a FIXED 60s window, and on a slow machine a
        // request takes seconds, so the window rolls over (and resets to zero)
        // before the loop ever reaches the ceiling. Priming keeps the assertion
        // about the contract instead of about request latency.
        $limiter = app(RateLimiterService::class);
        $response = null;

        // A prime + request pair can still straddle a window boundary; retry from a
        // clean counter if it does.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            for ($i = 0; $i < 30; $i++) {
                $limiter->hit('public', '127.0.0.1', 60);
            }

            $response = $this->get('/ref/SOMECODE');

            if ($response->getStatusCode() === 429) {
                break;
            }

            $this->clearPublicThrottle();
        }

        $response->assertStatus(429)
            ->assertJsonPath('code', 'RATE_LIMITED')
            ->assertHeader('Retry-After');
    }

    // ─── Custom AI exception → status mappings ───
    //
    // Rendered through the real exception handler (the same path a thrown exception
    // takes) against a JSON request, so a regression in the withExceptions() mappings
    // — e.g. one silently degrading to a 500 — is caught.

    private function render(\Throwable $e): \Symfony\Component\HttpFoundation\Response
    {
        $request = Request::create('/api/v1/anything', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);

        return app(ExceptionHandler::class)->render($request, $e);
    }

    public function test_insufficient_credits_exception_renders_as_402(): void
    {
        $response = $this->render(new InsufficientCreditsException(5.0, 20.0));
        $body = json_decode($response->getContent(), true);

        $this->assertSame(402, $response->getStatusCode());
        $this->assertSame('Insufficient credits', $body['error']);
        $this->assertEquals(5.0, $body['balance']);
        $this->assertEquals(20.0, $body['estimated_cost']);
    }

    public function test_credit_limit_exception_renders_as_402(): void
    {
        $response = $this->render(new CreditLimitException('daily', 0));
        $body = json_decode($response->getContent(), true);

        $this->assertSame(402, $response->getStatusCode());
        $this->assertSame('Credit limit exceeded', $body['error']);
        $this->assertSame('daily', $body['limit_type']);
    }

    public function test_integration_not_configured_renders_as_503(): void
    {
        $response = $this->render(new IntegrationNotConfiguredException('openai'));
        $body = json_decode($response->getContent(), true);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('Integration not available', $body['error']);
        $this->assertSame('openai', $body['integration']);
    }

    public function test_storage_write_failure_renders_as_500_json_for_the_api(): void
    {
        $response = $this->render(StorageWriteException::forUpload());
        $body = json_decode($response->getContent(), true);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Storage write failed', $body['error']);
    }

    public function test_an_unexpected_exception_renders_as_a_500_json_not_html(): void
    {
        // A genuinely unhandled error (no custom renderable) on a JSON request must
        // still come back as a 500 with a JSON content type — never an HTML error
        // page an API client can't parse.
        $response = $this->render(new \RuntimeException('boom'));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }

    // ─── sanity: an authenticated request is NOT one of the above ──

    public function test_an_authenticated_sanctum_request_is_not_rejected_as_guest(): void
    {
        Sanctum::actingAs($this->user());

        // listTemplates is a real endpoint; with a token it must NOT 401/403.
        $response = $this->getJson('/api/v1/ai/templates');
        $this->assertNotContains($response->getStatusCode(), [401, 403]);
    }
}
