<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiTool;
use App\Models\AiUsageLog;
use App\Models\Category;
use App\Models\ToolEmbed;
use App\Models\User;
use App\Http\Middleware\LicenseMiddleware;
use App\Services\AI\PromptBuilder;
use App\Services\AI\ToolCatalogCacheService;
use App\Http\Middleware\ThrottleAiRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Regression cover for the AI-tools production audit.
 *
 * Each test pins one bug that shipped: an embed that generated without its
 * password, a pre-flight estimate a client could shrink, a model the user picked
 * but never got, and a tool cache that baked in a global toggle.
 */
class AiToolsAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Without the first the license gate 403s every POST before the controller
        // runs — which would make the embed tests below "pass" on the wrong 403. The
        // throttler is dropped because its counters are shared across the suite, not
        // because anything here is exempt from rate limiting.
        $this->withoutMiddleware([LicenseMiddleware::class, ThrottleAiRequests::class]);

        $this->instance(
            \App\Services\NotificationEventService::class,
            Mockery::mock(\App\Services\NotificationEventService::class)->shouldIgnoreMissing(),
        );
    }

    private function category(): Category
    {
        return Category::create([
            'name' => 'Writing',
            'slug' => 'writing-'.uniqid(),
            'type' => 'ai_tool',
            'is_active' => true,
        ]);
    }

    private function tool(array $extra = []): AiTool
    {
        return AiTool::create(array_merge([
            'ulid' => (string) Str::ulid(),
            'name' => 'Blog Writer',
            'slug' => 'blog-writer-'.uniqid(),
            'type' => 'template',
            'category_id' => $this->category()->id,
            'prompt_system' => 'You write blogs.',
            'prompt_user' => 'Topic: {{topic}}',
            'access_level' => 'login',
            'is_active' => true,
        ], $extra));
    }

    private function model(string $slug, int $creditsPer1k): AiModel
    {
        return AiModel::create([
            'name' => $slug,
            'slug' => $slug,
            'provider' => 'openai',
            'type' => 'chat',
            'is_active' => true,
            'credits_per_1k' => $creditsPer1k,
            'cost_input_1k' => 0.001,
            'cost_output_1k' => 0.002,
            'max_tokens' => 4096,
        ]);
    }

    // ─── Embed: password / origin / active-tool enforcement ─────────────

    private function embed(User $owner, AiTool $tool, array $extra = []): ToolEmbed
    {
        return ToolEmbed::create(array_merge([
            'user_id' => $owner->id,
            'tool_slug' => $tool->slug,
            'is_active' => true,
        ], $extra));
    }

    public function test_password_protected_embed_refuses_to_run_without_an_unlock_grant(): void
    {
        $owner = User::factory()->create(['credits' => 100, 'is_active' => true]);
        $tool = $this->tool(['is_embeddable' => true, 'access_level' => 'guest']);
        $embed = $this->embed($owner, $tool, ['password_hash' => Hash::make('hunter2')]);

        // Previously the gate was client-side only: run() never checked it, so anyone
        // who knew the token could generate on the owner's credits.
        $this->postJson("/embed/{$embed->token}/run", ['fields' => ['topic' => 'seo']])
            ->assertStatus(403)
            ->assertSee('password protected', false);
    }

    public function test_unlock_issues_a_grant_that_lets_the_embed_run(): void
    {
        $owner = User::factory()->create(['credits' => 100, 'is_active' => true]);
        $tool = $this->tool(['is_embeddable' => true, 'access_level' => 'guest']);
        $embed = $this->embed($owner, $tool, ['password_hash' => Hash::make('hunter2')]);

        $grant = $this->postJson("/embed/{$embed->token}/unlock", ['password' => 'hunter2'])
            ->assertOk()
            ->json('unlock_token');

        $this->assertNotEmpty($grant);

        // The grant is bound to this embed, so a second embed must not accept it.
        $other = $this->embed($owner, $tool, ['password_hash' => Hash::make('other')]);

        $this->postJson("/embed/{$other->token}/run", [
            'fields' => ['topic' => 'seo'],
            'unlock_token' => $grant,
        ])->assertStatus(403);
    }

    public function test_embed_will_not_run_a_deactivated_tool(): void
    {
        $owner = User::factory()->create(['credits' => 100, 'is_active' => true]);
        $tool = $this->tool(['is_embeddable' => true, 'access_level' => 'guest', 'is_active' => false]);
        $embed = $this->embed($owner, $tool);

        $this->postJson("/embed/{$embed->token}/run", ['fields' => ['topic' => 'seo']])
            ->assertStatus(404);
    }

    public function test_embed_run_rejects_a_disallowed_origin(): void
    {
        $owner = User::factory()->create(['credits' => 100, 'is_active' => true]);
        $tool = $this->tool(['is_embeddable' => true, 'access_level' => 'guest']);
        $embed = $this->embed($owner, $tool, ['allowed_origins' => ['https://customer.com']]);

        $this->postJson(
            "/embed/{$embed->token}/run",
            ['fields' => ['topic' => 'seo']],
            ['Origin' => 'https://evil.example']
        )->assertStatus(403)->assertSee('Origin not allowed', false);
    }

    // ─── Pre-flight estimate cannot be shrunk via fields.model ──────────

    public function test_preflight_ignores_a_model_smuggled_through_the_fields_array(): void
    {
        $this->model('gpt-4o-mini', 1);
        $this->model('expensive-model', 5000);

        settings_set('default_ai_model', 'expensive-model', 'string', 'ai');
        settings_set('license_type', '2', 'integer', 'license');
        settings_set('subscriptions_enabled', '1', 'boolean', 'ai');

        $user = User::factory()->create([
            'credits' => 1,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $tool = $this->tool(['avg_output_tokens' => 2000]);

        // fields.model is not a validated input. Honouring it here let a client quote
        // a cheap model to the balance check while the run billed the expensive one.
        $this->actingAs($user)->postJson('/api/v1/generate/text', [
            'slug' => $tool->slug,
            'fields' => ['topic' => 'seo', 'model' => 'gpt-4o-mini'],
        ])->assertStatus(402);
    }

    // ─── Model precedence: quoted model == billed model ────────────────

    public function test_user_selected_model_wins_over_the_tools_override(): void
    {
        $this->model('gpt-4o-mini', 1);
        $this->model('gpt-4o', 20);

        $user = User::factory()->create(['is_active' => true]);
        $tool = $this->tool(['model_override' => 'gpt-4o-mini']);

        $completion = app(PromptBuilder::class)->build($tool, ['topic' => 'seo', 'model' => 'gpt-4o'], $user);

        // estimateCost(), buildRefine() and the CheckCredits pre-flight all already
        // put the user's pick first; build() put the override first, so the price the
        // user was quoted was not the model they got.
        $this->assertSame('gpt-4o', $completion->model);
    }

    public function test_tool_override_still_applies_when_no_model_is_chosen(): void
    {
        $this->model('gpt-4o-mini', 1);
        $this->model('gpt-4o', 20);
        settings_set('default_ai_model', 'gpt-4o-mini', 'string', 'ai');

        $user = User::factory()->create(['is_active' => true]);
        $tool = $this->tool(['model_override' => 'gpt-4o']);

        $completion = app(PromptBuilder::class)->build($tool, ['topic' => 'seo'], $user);

        $this->assertSame('gpt-4o', $completion->model);
    }

    public function test_refine_falls_back_to_a_real_model_when_the_client_sends_an_empty_one(): void
    {
        $this->model('gpt-4o-mini', 1);
        settings_set('default_ai_model', 'gpt-4o-mini', 'string', 'ai');

        $user = User::factory()->create(['is_active' => true]);
        $tool = $this->tool();

        // The tool page sends `model: ''` for any tool without a model picker. The old
        // `??` chain passed that straight through, so Improve hit the provider with a
        // blank model name.
        $completion = app(PromptBuilder::class)
            ->buildRefine($tool, 'some content', 'make it punchier', '', $user);

        $this->assertSame('gpt-4o-mini', $completion->model);
    }

    // ─── Recently-used tools ───────────────────────────────────────────

    public function test_recently_used_tools_are_listed_from_the_tool_slug_column(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $tool = $this->tool();

        AiUsageLog::create([
            'user_id' => $user->id,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'type' => 'tool',
            'tool_slug' => $tool->slug,
            'input_tokens' => 10,
            'output_tokens' => 20,
            'cost_usd' => 0.001,
            'credits_used' => 1,
            'status' => 'completed',
            // TokenGuard stores the slug here — never as metadata['tool_slug'], which
            // is what the controller used to read, so the list was always empty.
            'metadata' => ['template_slug' => $tool->slug],
        ]);

        $this->actingAs($user)
            ->get('/ai-tools')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('recentlyUsed', 1)
                ->where('recentlyUsed.0.slug', $tool->slug));
    }

    // ─── Tool cache must not bake in a global toggle ───────────────────

    public function test_re_enabling_a_global_toggle_takes_effect_without_waiting_for_the_cache(): void
    {
        $tool = $this->tool(['show_improve' => true, 'max_variants' => 3]);

        settings_set('global_tools_improve_enabled', false, 'boolean', 'theme');
        settings_set('global_tools_variations_enabled', false, 'boolean', 'theme');

        $catalog = app(ToolCatalogCacheService::class);

        // Warm the cache while the toggles are OFF.
        $off = $catalog->toolBySlug($tool->slug);
        $this->assertFalse($off['show_improve']);
        $this->assertSame(1, $off['max_variants']);

        // Turn them back ON. The cached row must still carry the tool's OWN value, so
        // the override layer can re-enable it; previously the accessor had already
        // ANDed the OFF setting into the cache and the feature stayed dead for an hour.
        settings_set('global_tools_improve_enabled', true, 'boolean', 'theme');
        settings_set('global_tools_variations_enabled', true, 'boolean', 'theme');

        $on = $catalog->toolBySlug($tool->slug);
        $this->assertTrue($on['show_improve']);
        $this->assertSame(3, $on['max_variants']);
    }
}
