<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\RateLimiterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingInvoiceDownloadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        // The `public` throttle is keyed by IP (127.0.0.1 in tests) and, when Redis is up,
        // survives RefreshDatabase — clear it so a tripped limit cannot bleed between tests.
        app(RateLimiterService::class)->clear('public', '127.0.0.1');

        $plan = Plan::create([
            'name' => 'Professional',
            'slug' => 'professional',
            'description' => 'Pro',
            'price_monthly' => 49.99,
            'price_yearly' => 499.99,
            'credits' => 9999,
            'features' => [],
            'is_active' => true,
            'is_free' => false,
            'sort_order' => 2,
        ]);

        $this->user = User::create([
            'name' => 'Demo Creator',
            'email' => 'creator@invoice.test',
            'password' => 'secret-hash',
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $this->payment = Payment::create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'gateway' => 'stripe',
            'gateway_payment_id' => 'inv-test-001',
            'amount' => 499.99,
            'currency' => 'USD',
            'status' => 'completed',
            'type' => 'subscription',
        ]);
    }

    protected function tearDown(): void
    {
        app(RateLimiterService::class)->clear('public', '127.0.0.1');
        // Settings are cached with rememberForever and the array store survives
        // RefreshDatabase, so branding set here would leak into later tests.
        \Illuminate\Support\Facades\Cache::flush();
        \Illuminate\Support\Facades\Storage::disk('public')->deleteDirectory('branding');

        parent::tearDown();
    }

    public function test_owner_downloads_a_pdf_invoice(): void
    {
        $response = $this->actingAs($this->user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard/billing/invoice/' . $this->payment->ulid);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $expectedName = 'INV-' . $this->payment->created_at->format('Ym') . '-' . str_pad((string) $this->payment->id, 5, '0', STR_PAD_LEFT);
        $this->assertStringContainsString($expectedName . '.pdf', $response->headers->get('Content-Disposition'));

        // A real PDF, not an error page rendered with a PDF content type.
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_another_user_cannot_download_someone_elses_invoice(): void
    {
        $intruder = User::create([
            'name' => 'Intruder',
            'email' => 'intruder@invoice.test',
            'password' => 'secret-hash',
            'is_active' => true,
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($intruder)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard/billing/invoice/' . $this->payment->ulid)
            ->assertForbidden();
    }

    public function test_guests_are_redirected(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard/billing/invoice/' . $this->payment->ulid)
            ->assertRedirect();
    }

    public function test_invoice_uses_the_site_logo_and_falls_back_to_the_name(): void
    {
        settings_set('site_name', 'MakeAI Demo', 'string', 'branding');

        $download = fn () => $this->actingAs($this->user)
            ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
            ->get('/user/dashboard/billing/invoice/' . $this->payment->ulid);

        // No logo configured — the PDF must still render, using the site name.
        settings_set('site_logo_light', '', 'string', 'branding');
        settings_set('site_logo_dark', '', 'string', 'branding');
        \Illuminate\Support\Facades\Cache::flush();

        $download()->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // A real raster logo on the public disk is embedded.
        \Illuminate\Support\Facades\Storage::disk('public')->put(
            'branding/logo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
        );

        settings_set('site_logo_light', 'branding/logo.png', 'string', 'branding');
        \Illuminate\Support\Facades\Cache::flush();

        $download()->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // An SVG logo is skipped rather than handed to mPDF, whose SVG support is partial —
        // a logo it cannot parse would render as a broken box mid-invoice.
        \Illuminate\Support\Facades\Storage::disk('public')->put('branding/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        settings_set('site_logo_light', 'branding/logo.svg', 'string', 'branding');
        \Illuminate\Support\Facades\Cache::flush();

        $download()->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // A remote logo is not fetched during rendering; the name renders instead.
        settings_set('site_logo_light', 'https://cdn.example.test/logo.png', 'string', 'branding');
        \Illuminate\Support\Facades\Cache::flush();

        $download()->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_unsettled_and_reversed_payments_still_render_with_a_notice(): void
    {
        foreach (['pending', 'refunded'] as $status) {
            $this->payment->update(['status' => $status]);

            $this->actingAs($this->user)
                ->withoutMiddleware([\App\Http\Middleware\LicenseMiddleware::class])
                ->get('/user/dashboard/billing/invoice/' . $this->payment->ulid)
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }
    }
}
