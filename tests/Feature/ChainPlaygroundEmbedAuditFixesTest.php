<?php

namespace Tests\Feature;

use App\Events\ChainCompleted;
use App\Events\ChainStepCompleted;
use App\Http\Middleware\LicenseMiddleware;
use App\Http\Middleware\ThrottleAiRequests;
use App\Jobs\RunToolChainJob;
use App\Models\AiKey;
use App\Models\AiModel;
use App\Models\AiTool;
use App\Models\Category;
use App\Models\ToolChain;
use App\Models\ToolChainRun;
use App\Models\ToolEmbed;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\PlaygroundService;
use App\Services\ToolChainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Regression cover for the playground / tool-embed / chain audit.
 *
 * Each test pins one bug that shipped: chains that ran every tool with no inputs at
 * all, a step reference that always resolved to the previous step, a playground that
 * would bill any model the client named, and an embed allowlist nothing could set.
 */
class ChainPlaygroundEmbedAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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
            'fields' => [
                ['name' => 'topic', 'key' => 'topic', 'label' => 'Topic', 'type' => 'textarea', 'required' => true],
            ],
        ], $extra));
    }

    // ─── Chains: steps must actually receive their inputs ───────────────

    public function test_a_chain_step_feeds_the_previous_output_into_the_next_tool(): void
    {
        $user = User::factory()->create(['credits' => 500, 'is_active' => true]);
        $first = $this->tool();
        $second = $this->tool();

        $chain = ToolChain::create([
            'user_id' => $user->id,
            'name' => 'Draft then polish',
            'steps' => [
                ['step' => 1, 'tool_slug' => $first->slug, 'static_inputs' => [], 'field_map' => ['topic' => '{{input}}']],
                ['step' => 2, 'tool_slug' => $second->slug, 'static_inputs' => [], 'field_map' => ['topic' => '{{previous_output}}']],
            ],
        ]);

        // The builder used to persist an empty field_map for every step, so each tool
        // ran with no fields and PromptBuilder emitted [MISSING: topic].
        $seen = [];
        $ai = Mockery::mock(AiService::class);
        $ai->shouldReceive('runTemplate')
            ->twice()
            ->andReturnUsing(function (User $u, AiTool $tool, array $inputs) use (&$seen) {
                $seen[] = $inputs;

                return [
                    'content' => 'output of '.$tool->slug,
                    'input_tokens' => 10,
                    'output_tokens' => 20,
                    'credits_used' => 1.5,
                ];
            });

        // Scoped to the chain events on purpose: a blanket Event::fake() would also
        // swallow the Eloquent creating hooks that assign each row its ULID.
        Event::fake([ChainStepCompleted::class, ChainCompleted::class]);

        $this->instance(AiService::class, $ai);
        $this->app->forgetInstance(ToolChainService::class);

        (new RunToolChainJob($chain, $user, 'write about otters'))->handle(app(ToolChainService::class));

        $this->assertSame('write about otters', $seen[0]['topic']);
        $this->assertSame('output of '.$first->slug, $seen[1]['topic']);

        $run = ToolChainRun::where('chain_id', $chain->id)->firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertSame('write about otters', $run->input);
        // total_credits existed in the schema but nothing ever wrote it.
        $this->assertEqualsWithDelta(3.0, (float) $run->total_credits, 0.001);
        $this->assertSame(60, (int) $run->total_tokens);
        $this->assertCount(2, $run->step_outputs);
    }

    // ─── Chains: billing works in both credit modes ─────────────────────

    private function useQuotaMode(): void
    {
        settings_set('license_type', '1', 'integer', 'license');
        settings_set('subscriptions_enabled', '0', 'boolean', 'ai');
    }

    private function useMeteredMode(): void
    {
        settings_set('license_type', '2', 'integer', 'license');
        settings_set('subscriptions_enabled', '1', 'boolean', 'ai');
    }

    private function chainOf(User $user): ToolChain
    {
        $tool = $this->tool();

        return ToolChain::create([
            'user_id' => $user->id,
            'name' => 'Two step',
            'steps' => [
                ['step' => 1, 'tool_slug' => $tool->slug, 'static_inputs' => [], 'field_map' => ['topic' => '{{input}}']],
                ['step' => 2, 'tool_slug' => $tool->slug, 'static_inputs' => [], 'field_map' => ['topic' => '{{previous_output}}']],
            ],
        ]);
    }

    public function test_quota_mode_lets_a_drained_wallet_still_start_a_chain(): void
    {
        $this->useQuotaMode();
        $this->model('gpt-4o-mini', 1);
        settings_set('user_daily_credit_limit', '0', 'string', 'ai');

        // Quota mode (Regular license) has no top-up, so the wallet is not a wall —
        // the allowance is. A zero balance must not block the run.
        $user = User::factory()->create(['credits' => 0, 'is_active' => true]);

        Queue::fake();

        $this->actingAs($user)
            ->post("/user/dashboard/chains/{$this->chainOf($user)->ulid}/run", ['input' => 'go'])
            ->assertSessionHas('success');
    }

    public function test_metered_mode_blocks_a_chain_the_user_cannot_pay_for(): void
    {
        $this->useMeteredMode();
        $this->model('gpt-4o-mini', 1);

        $user = User::factory()->create(['credits' => 0, 'is_active' => true]);

        // Metered mode: the wallet is real money, so the pre-flight refuses up front
        // rather than queueing a job that dies on the first step.
        $this->actingAs($user)
            ->post("/user/dashboard/chains/{$this->chainOf($user)->ulid}/run", ['input' => 'go'])
            ->assertSessionHas('error');
    }

    public function test_quota_mode_still_enforces_the_daily_allowance_on_a_chain(): void
    {
        $this->useQuotaMode();
        $this->model('gpt-4o-mini', 1);
        settings_set('user_daily_credit_limit', '5', 'string', 'ai');

        $user = User::factory()->create([
            'credits' => 999,
            'credits_used_today' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post("/user/dashboard/chains/{$this->chainOf($user)->ulid}/run", ['input' => 'go'])
            ->assertSessionHas('error');
    }

    public function test_step_n_output_resolves_that_step_not_the_previous_one(): void
    {
        $service = app(ToolChainService::class);

        $outputs = [1 => 'first', 2 => 'second', 3 => 'third'];

        // The old callback discarded the captured N and returned the previous output
        // for every {{step_N_output}}, so step 1's text was unreachable from step 4.
        $this->assertSame('first', $service->resolveTemplate('{{step_1_output}}', $outputs, 'in'));
        $this->assertSame('second', $service->resolveTemplate('{{step_2_output}}', $outputs, 'in'));
        $this->assertSame('third', $service->resolveTemplate('{{previous_output}}', $outputs, 'in'));
        $this->assertSame('in', $service->resolveTemplate('{{input}}', $outputs, 'in'));
        $this->assertSame('first + in', $service->resolveTemplate('{{step_1_output}} + {{input}}', $outputs, 'in'));
    }

    public function test_a_failed_chain_records_why_instead_of_swallowing_it(): void
    {
        $user = User::factory()->create(['credits' => 500, 'is_active' => true]);
        $chain = ToolChain::create([
            'user_id' => $user->id,
            'name' => 'Broken',
            'steps' => [
                ['step' => 1, 'tool_slug' => $this->tool()->slug, 'static_inputs' => [], 'field_map' => ['topic' => '{{input}}']],
                ['step' => 2, 'tool_slug' => $this->tool()->slug, 'static_inputs' => [], 'field_map' => ['topic' => '{{previous_output}}']],
            ],
        ]);

        $ai = Mockery::mock(AiService::class);
        $ai->shouldReceive('runTemplate')->once()->andThrow(new \RuntimeException('provider exploded'));

        // Scoped to the chain events on purpose: a blanket Event::fake() would also
        // swallow the Eloquent creating hooks that assign each row its ULID.
        Event::fake([ChainStepCompleted::class, ChainCompleted::class]);

        $this->instance(AiService::class, $ai);
        $this->app->forgetInstance(ToolChainService::class);

        (new RunToolChainJob($chain, $user, 'go'))->handle(app(ToolChainService::class));

        $run = ToolChainRun::where('chain_id', $chain->id)->firstOrFail();
        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('provider exploded', (string) $run->error);
    }

    public function test_chain_update_rejects_a_step_with_no_tool(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $tool = $this->tool();

        $chain = ToolChain::create([
            'user_id' => $user->id,
            'name' => 'Mine',
            'steps' => [
                ['step' => 1, 'tool_slug' => $tool->slug, 'static_inputs' => [], 'field_map' => []],
                ['step' => 2, 'tool_slug' => $tool->slug, 'static_inputs' => [], 'field_map' => []],
            ],
        ]);

        // update() only validated `steps` as a bare array, so it could persist steps
        // that store() would have rejected outright.
        $this->actingAs($user)
            ->put("/user/dashboard/chains/{$chain->ulid}", [
                'name' => 'Mine',
                'steps' => [
                    ['tool_slug' => ''],
                    ['tool_slug' => $tool->slug],
                ],
            ])
            ->assertSessionHasErrors('steps.0.tool_slug');
    }

    // ─── Playground: only admin-enabled models may be billed ────────────

    public function test_playground_rejects_a_model_the_admin_never_enabled(): void
    {
        $user = User::factory()->create(['credits' => 500, 'is_active' => true]);

        AiKey::create(['provider' => 'openai', 'api_key' => 'sk-test', 'is_active' => true]);
        $this->model('gpt-4o-mini', 1);

        // The picker only offers enabled models, but the endpoint is a plain POST: an
        // unlisted model bills at TokenGuard's generic fallback rate, not its own.
        $this->actingAs($user)
            ->postJson('/user/dashboard/playground/run', [
                'provider' => 'openai',
                'model' => 'o1-pro-secret',
                'messages' => [['role' => 'user', 'content' => 'hi']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('model');
    }

    public function test_playground_rejects_an_unregistered_provider_instead_of_500ing(): void
    {
        $user = User::factory()->create(['credits' => 500, 'is_active' => true]);

        $this->actingAs($user)
            ->postJson('/user/dashboard/playground/run', [
                'provider' => 'not-a-provider',
                'model' => 'gpt-4o-mini',
                'messages' => [['role' => 'user', 'content' => 'hi']],
            ])
            ->assertStatus(422);
    }

    public function test_playground_snapshots_share_without_redis(): void
    {
        // share() used the Redis facade directly while the app runs the database
        // cache driver, so the Share button 500'd on any install without Redis.
        $service = app(PlaygroundService::class);

        $uuid = $service->share(['prompt' => 'hello', 'params_left' => [], 'params_right' => []]);

        $this->assertSame('hello', $service->getShare($uuid)['prompt']);
        $this->assertNull($service->getShare('missing-uuid'));
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

    // ─── Embeds: allowlist and embeddability ────────────────────────────

    public function test_embed_cannot_be_created_for_a_tool_that_is_not_embeddable(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $tool = $this->tool(['is_embeddable' => false]);

        // The tool picker listed every active tool, so users could publish an embed
        // whose page then 403'd forever.
        $this->actingAs($user)
            ->post('/user/dashboard/tool-embeds', ['tool_slug' => $tool->slug])
            ->assertSessionHasErrors('tool_slug');

        $this->assertDatabaseCount('tool_embeds', 0);
    }

    public function test_embed_stores_the_domain_allowlist_it_is_given(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $tool = $this->tool(['is_embeddable' => true]);

        $this->actingAs($user)
            ->post('/user/dashboard/tool-embeds', [
                'tool_slug' => $tool->slug,
                'allowed_origins' => ['example.com', '  ', 'app.example.com'],
            ])
            ->assertSessionHasNoErrors();

        // With no UI for it this was always null, which meant frame-ancestors '*'.
        $this->assertSame(['example.com', 'app.example.com'], ToolEmbed::first()->allowed_origins);
    }

    public function test_embed_update_rejects_a_nested_origin_array(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $tool = $this->tool(['is_embeddable' => true]);

        $embed = ToolEmbed::create([
            'user_id' => $user->id,
            'tool_slug' => $tool->slug,
            'is_active' => true,
        ]);

        // update() was missing store()'s allowed_origins.* rule, so a nested array got
        // persisted and every later load of the embed died in array_map('trim', …).
        $this->actingAs($user)
            ->put("/user/dashboard/tool-embeds/{$embed->ulid}", [
                'allowed_origins' => [['evil.com']],
            ])
            ->assertSessionHasErrors('allowed_origins.0');

        $this->assertNull($embed->fresh()->allowed_origins);
    }
}
