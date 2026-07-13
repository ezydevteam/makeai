<?php

namespace Addons\AiAssistant\Tests;

use App\Models\Addon;
use App\Models\AiModel;
use App\Models\Plan;
use App\Models\User;
use App\Services\AI\Drivers\ThinkingAgent;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Ai;
use Tests\TestCase;

/**
 * Shared bootstrap for the AI Assistant addon's tests.
 *
 * Two things this has to do that a plain Tests\TestCase does not:
 *
 * 1. REGISTER THE ADDON. AppServiceProvider::boot() registers addon service providers
 *    from the `addons` table — which, under RefreshDatabase, does not exist yet when the
 *    app boots. So in a test the addon's routes and migrations are simply absent unless
 *    we mark the addon active and register its provider by hand, after the schema is up.
 *
 * 2. FAKE THE PROVIDER. Every chat request ends in a real HTTP call otherwise. The
 *    Laravel AI SDK's agent fake is the only seam available: ProviderRegistry::resolve()
 *    is static and hands back a LaravelAiDriver, but that driver drives its stream through
 *    an Agent (ThinkingAgent), which the SDK resolves through AiManager — and AiManager
 *    honours Ai::fakeAgent(). See fakeProvider() below.
 */
abstract class AssistantTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Addon::updateOrCreate(['slug' => 'ai-assistant'], [
            'name' => 'AI Assistant',
            'version' => '1.0.0',
            'is_active' => true,
            'manifest' => json_decode(File::get(base_path('addons/ai-assistant/addon.json')), true),
        ]);

        // Addon migrations live outside database/migrations, so migrate:fresh never saw them.
        $this->artisan('migrate', ['--path' => 'addons/ai-assistant/database/migrations']);

        // Not PSR-4 autoloadable (the manifest namespace maps to addons/ai-assistant/app/),
        // which is why AddonService require_once's it too.
        require_once base_path('addons/ai-assistant/AddonServiceProvider.php');
        $this->app->register(\Addons\AiAssistant\AddonServiceProvider::class);

        // Laravel refreshes the router's name/action lookup tables in an app->booted()
        // callback. In production the addon provider is registered from AppServiceProvider
        // ::boot(), i.e. before that fires; here we register after it, so route('addon.
        // ai-assistant.*') would not resolve without this. (URL matching works either way.)
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        // The frontend chat route lives under `web`, which appends LicenseMiddleware, and
        // its path matches `api/*` — so an unverified license returns 403 LICENSE_INVALID
        // before the request ever reaches the controller. Production installs are verified;
        // a bare test database is not. Verify it so the route behaves as it does in prod.
        $this->verifyLicense();

        // The `throttle:30,1` on the chat routes is a custom IP-keyed limiter whose counter
        // lives in the cache and is NOT rolled back by RefreshDatabase, so it bleeds across
        // tests run from the same loopback IP and 429s later cases. Throttling is orthogonal
        // to everything under test here — the addon enforces its OWN daily limit separately —
        // so switch just this one middleware off.
        $this->withoutMiddleware(\App\Http\Middleware\ThrottleAiRequests::class);
    }

    /** Mark the install's license verified, as a real deployment would be. */
    protected function verifyLicense(): void
    {
        settings_set('license_status', 'valid', 'string', 'license');
        // license_verified() caches this under its own key for an hour — clear it so the
        // freshly-written status is read this request.
        \Illuminate\Support\Facades\Cache::forget('license.status');
    }

    // ─── addon settings ──────────────────────────────────────

    protected function enableFrontend(string $showTo = 'all'): void
    {
        addon_setting_set('ai-assistant', 'enabled', true, 'boolean');
        addon_setting_set('ai-assistant', 'show_to', $showTo);
    }

    protected function setLimits(int $guest = 0, int $member = 0, int $pro = 0): void
    {
        addon_setting_set('ai-assistant', 'guest_daily_message_limit', $guest, 'integer');
        addon_setting_set('ai-assistant', 'daily_message_limit', $member, 'integer');
        addon_setting_set('ai-assistant', 'pro_daily_message_limit', $pro, 'integer');
    }

    // ─── license / credit mode ───────────────────────────────

    /** Regular license (or subscriptions off): no sellable paid tier, credits are an allowance. */
    protected function useQuotaMode(): void
    {
        settings_set('license_type', '1', 'integer', 'license');
        settings_set('subscriptions_enabled', '0', 'boolean', 'ai');
    }

    /** Extended license + subscriptions on: isProAvailable() is true, credits are a wallet. */
    protected function useMeteredMode(): void
    {
        settings_set('license_type', '2', 'integer', 'license');
        settings_set('subscriptions_enabled', '1', 'boolean', 'ai');
    }

    // ─── users ───────────────────────────────────────────────

    protected function freeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'is_active' => true,
            'credits' => 100,
        ], $attributes));
    }

    /**
     * A user User::isPro() says yes to: a paid (non-free) plan with no lapse date.
     */
    protected function proUser(array $attributes = []): User
    {
        $plan = Plan::create([
            'name' => 'Pro',
            'slug' => 'pro-'.uniqid(),
            'price' => 29,
            'credits' => 1000,
            'is_free' => false,
            'is_active' => true,
        ]);

        return User::factory()->create(array_merge([
            'is_active' => true,
            'credits' => 100,
            'plan_id' => $plan->id,
            'subscription_ends_at' => null,
        ], $attributes));
    }

    /**
     * Authenticate as a super-admin on the `admin` guard, which is what the admin chat
     * routes sit behind. Mirrors the setup in tests/Feature/AddonLicenseTest.php.
     */
    protected function actingAsAdmin(): \App\Models\Admin
    {
        $role = \App\Models\AdminRole::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'is_system' => true],
        );

        config(['auth.providers.admins.super_admin_slug' => 'super-admin']);

        $admin = \App\Models\Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin+' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $admin->load('role');

        $this->actingAs($admin, 'admin');

        return $admin;
    }

    // ─── AI plumbing ─────────────────────────────────────────

    protected function seedChatModel(): AiModel
    {
        return AiModel::create([
            'slug' => 'gpt-4o-mini',
            'name' => 'GPT-4o Mini',
            'provider' => 'openai',
            'type' => 'chat',
            'is_active' => true,
            'cost_input_1k' => 0.00015,
            'cost_output_1k' => 0.0006,
            'credits_per_1k' => 1,
            'credits_auto' => true,
            'max_tokens' => 16384,
        ]);
    }

    /**
     * Intercept the provider. $responses may be a list of canned replies or a closure
     * (throw from the closure to simulate a provider blowing up mid-stream).
     */
    protected function fakeProvider(array|\Closure $responses = ['Hello from the assistant.']): void
    {
        Ai::fakeAgent(ThinkingAgent::class, $responses);
    }

    // ─── SSE assertions ──────────────────────────────────────

    /**
     * Decode the typed JSON frames of an SSE response into an array of payloads.
     */
    protected function frames(\Illuminate\Testing\TestResponse $response): array
    {
        $frames = [];

        foreach (explode("\n", $response->streamedContent()) as $line) {
            if (str_starts_with($line, 'data: ')) {
                $frames[] = json_decode(substr($line, 6), true);
            }
        }

        return $frames;
    }

    protected function frameTypes(\Illuminate\Testing\TestResponse $response): array
    {
        return array_column($this->frames($response), 'type');
    }

    protected function streamText(\Illuminate\Testing\TestResponse $response): string
    {
        return collect($this->frames($response))
            ->where('type', 'token')
            ->pluck('content')
            ->implode('');
    }

    /**
     * The error frame of a refused stream, or null when the stream completed.
     */
    protected function errorFrame(\Illuminate\Testing\TestResponse $response): ?array
    {
        return collect($this->frames($response))->firstWhere('type', 'error');
    }
}
