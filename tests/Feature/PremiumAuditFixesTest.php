<?php

namespace Tests\Feature;

use App\Http\Controllers\User\CreditTopupController;
use App\Jobs\RunToolChainJob;
use App\Models\AiTool;
use App\Models\Currency;
use App\Models\ToolChain;
use App\Models\ToolEmbed;
use App\Models\User;
use App\Services\AI\ToolAccessService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression tests for the premium-system audit fixes:
 *  #1 pro/plan access gating on the API template endpoint, tool chains, and embeds
 *  #5 convert_currency divide-by-zero on a bad exchange rate
 *  #6 top-up credit float-drift shorting a credit
 */
class PremiumAuditFixesTest extends TestCase
{
    private function meteredMode(): void
    {
        // Extended license + billing on, so a 'premium' tool genuinely gates
        // (non-pro users are denied with 'upgrade') rather than 'pro_unavailable'.
        settings_set('license_type', '2', 'integer', 'license');
        settings_set('subscriptions_enabled', '1', 'boolean', 'ai');
    }

    private function premiumTool(array $overrides = []): AiTool
    {
        return AiTool::create(array_merge([
            'ulid' => (string) Str::ulid(),
            'name' => 'Premium Tool',
            'slug' => 'premium-'.uniqid(),
            'type' => 'tool',
            'is_active' => true,
            'access_level' => 'premium',
        ], $overrides));
    }

    private function openTool(): AiTool
    {
        return AiTool::create([
            'ulid' => (string) Str::ulid(),
            'name' => 'Open Tool',
            'slug' => 'open-'.uniqid(),
            'type' => 'tool',
            'is_active' => true,
            'access_level' => 'guest',
        ]);
    }

    // ─── #1 access gating ────────────────────────────────────────

    public function test_api_template_endpoint_blocks_pro_tool_for_non_pro_user(): void
    {
        // Real HTTP path via the now-installed Sanctum guard.
        $this->meteredMode();
        $tool = $this->premiumTool();
        \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create()); // non-pro token

        $this->postJson("/api/v1/ai/template/{$tool->id}", ['inputs' => ['topic' => 'x']])
            ->assertStatus(403);
    }

    public function test_access_gate_allows_open_tool(): void
    {
        // Sanity: the new gate doesn't block a tool the user CAN access.
        $this->meteredMode();
        $tool = $this->openTool();
        $user = User::factory()->create();

        $this->assertTrue(app(ToolAccessService::class)->checkAccess($tool, $user)->allowed);
    }

    public function test_chain_run_blocks_inaccessible_tool(): void
    {
        Queue::fake();
        $this->meteredMode();

        $open = $this->openTool();
        $premium = $this->premiumTool();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $chain = ToolChain::create([
            'user_id' => $user->id,
            'name' => 'Chain',
            'steps' => [
                ['step' => 1, 'tool_slug' => $open->slug, 'static_inputs' => [], 'field_map' => []],
                ['step' => 2, 'tool_slug' => $premium->slug, 'static_inputs' => [], 'field_map' => []],
            ],
        ]);

        $this->actingAs($user)->post(route('user.dashboard.chains.run', $chain));

        Queue::assertNotPushed(RunToolChainJob::class);
    }

    public function test_embed_run_blocks_pro_tool_for_non_pro_owner(): void
    {
        $this->meteredMode();
        $tool = $this->premiumTool(['is_embeddable' => true]);
        $owner = User::factory()->create(); // non-pro

        $embed = ToolEmbed::create([
            'token' => Str::random(24),
            'tool_slug' => $tool->slug,
            'user_id' => $owner->id,
            'is_active' => true,
        ]);

        $this->postJson(route('embed.run', $embed->token), ['fields' => ['topic' => 'x']])
            ->assertStatus(403);
    }

    // ─── #5 currency divide-by-zero ──────────────────────────────

    public function test_convert_currency_falls_back_on_zero_rate(): void
    {
        Currency::create(['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'exchange_rate' => 1, 'is_active' => true]);
        Currency::create(['code' => 'ZZZ', 'symbol' => 'Z', 'name' => 'Bad Rate', 'exchange_rate' => 0, 'is_active' => true]);

        // Rate 0 must not divide-by-zero (INF); falls back to the input amount.
        $this->assertSame(50.0, convert_currency(50.0, 'ZZZ', 'USD'));
    }

    // ─── #6 top-up float drift ───────────────────────────────────

    public function test_topup_credit_calc_has_no_float_drift(): void
    {
        $method = new ReflectionMethod(CreditTopupController::class, 'calculateCredits');
        $method->setAccessible(true);
        $controller = new CreditTopupController;

        // 19.99 / 0.01 = 1998.9999… → without round() this floors to 1998.
        $result = $method->invoke($controller, 19.99, 0.01, []);
        $this->assertSame(1999, $result['base_credits']);

        // Zero price is guarded (no DivisionByZeroError).
        $guarded = $method->invoke($controller, 10.0, 0.0, []);
        $this->assertSame(0, $guarded['base_credits']);
    }
}
