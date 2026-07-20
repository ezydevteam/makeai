<?php

namespace Tests\Feature;

use App\Http\Controllers\User\EmbedController;
use App\Http\Middleware\LicenseMiddleware;
use App\Http\Middleware\ThrottleAiRequests;
use App\Models\AiKey;
use App\Models\AiModel;
use App\Models\AiTool;
use App\Models\AiUsageLog;
use App\Models\Category;
use App\Models\ToolEmbed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\FakeAiDriver;
use Tests\TestCase;

/**
 * The embed stream is what a public visitor actually hits, and it had no coverage at
 * all — the existing embed tests only assert the 403s. These drive it with a scripted
 * driver, because each of the paths below silently generated for free.
 */
class EmbedStreamBillingTest extends TestCase
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

        // Metered mode: the wallet is the thing to watch drain.
        settings_set('license_type', '2', 'integer', 'license');
        settings_set('subscriptions_enabled', '1', 'boolean', 'ai');

        AiKey::create(['provider' => 'openai', 'api_key' => 'sk-test', 'is_active' => true]);

        AiModel::create([
            'name' => 'gpt-4o-mini',
            'slug' => 'gpt-4o-mini',
            'provider' => 'openai',
            'type' => 'chat',
            'is_active' => true,
            'credits_per_1k' => 1,
            'cost_input_1k' => 0.001,
            'cost_output_1k' => 0.002,
            'max_tokens' => 4096,
        ]);
    }

    private function embed(User $owner): ToolEmbed
    {
        $category = Category::create([
            'name' => 'Writing',
            'slug' => 'writing-'.uniqid(),
            'type' => 'ai_tool',
            'is_active' => true,
        ]);

        $tool = AiTool::create([
            'ulid' => (string) Str::ulid(),
            'name' => 'Blog Writer',
            'slug' => 'blog-writer-'.uniqid(),
            'type' => 'template',
            'category_id' => $category->id,
            'prompt_system' => 'You write blogs.',
            'prompt_user' => 'Topic: {{topic}}',
            'access_level' => 'guest',
            'is_active' => true,
            'is_embeddable' => true,
            'model_override' => 'gpt-4o-mini',
        ]);

        return ToolEmbed::create([
            'user_id' => $owner->id,
            'tool_slug' => $tool->slug,
            'is_active' => true,
        ]);
    }

    /** 1000 in + 1000 out @ credits_per_1k = 1 → 2 credits. */
    private function usageChunk(): array
    {
        return [
            'input_tokens' => 1000,
            'output_tokens' => 1000,
            'model' => 'gpt-4o-mini',
        ];
    }

    public function test_a_completed_embed_run_bills_the_owner(): void
    {
        $owner = User::factory()->create(['credits' => 10, 'is_active' => true]);
        $embed = $this->embed($owner);

        FakeAiDriver::bind(['Hello', ' world', $this->usageChunk()]);

        $response = $this->post("/embed/{$embed->token}/run", ['fields' => ['topic' => 'otters']]);
        $body = $response->streamedContent();

        $this->assertStringContainsString('Hello', $body);
        $this->assertEquals(8.0, (float) $owner->fresh()->credits);

        $this->assertDatabaseHas('ai_usage_logs', [
            'user_id' => $owner->id,
            'type' => 'embed',
            'status' => 'completed',
            'credits_used' => 2,
        ]);
    }

    public function test_reasoning_chunks_do_not_break_billing_on_an_aborted_run(): void
    {
        $owner = User::factory()->create(['credits' => 10, 'is_active' => true]);
        $embed = $this->embed($owner);

        // A reasoning model yields arrays for its reasoning events, and the loop took
        // ANY array as the usage payload. On a completed run the real usage arrives last
        // and overwrites them, so this only bit when the visitor left: the loop broke
        // with $usage = ['reasoning_end' => true], the charge died on a missing
        // input_tokens, and the visitor got "Generation failed" on an unbilled run.
        FakeAiDriver::bind([
            ['reasoning_start' => true],
            ['reasoning' => 'thinking...'],
            ['reasoning_end' => true],
            'Ans', 'wer',
            $this->usageChunk(),
        ]);

        $this->disconnectAfterFirstToken();

        $body = $this->post("/embed/{$embed->token}/run", ['fields' => ['topic' => 'otters']])
            ->streamedContent();

        $this->assertStringNotContainsString('Generation failed', $body);
        $this->assertEquals(8.0, (float) $owner->fresh()->credits);
    }

    /**
     * Stand in for the visitor closing the tab after the first token — connection_aborted()
     * always reports "connected" under PHPUnit, and that is the branch that leaked.
     */
    private function disconnectAfterFirstToken(): void
    {
        $this->app->bind(EmbedController::class, fn () => new class extends EmbedController
        {
            private int $chunks = 0;

            protected function clientDisconnected(): bool
            {
                return ++$this->chunks > 1;
            }
        });
    }

    public function test_an_aborted_embed_stream_is_still_billed(): void
    {
        $owner = User::factory()->create(['credits' => 10, 'is_active' => true]);
        $embed = $this->embed($owner);

        FakeAiDriver::bind(['Hel', 'lo', ' world', $this->usageChunk()]);

        // The old loop broke out on disconnect, so the usage chunk never arrived and the
        // run was billed to nobody — while the provider still charged the operator for
        // the whole completion it had already generated.
        $this->disconnectAfterFirstToken();

        $body = $this->post("/embed/{$embed->token}/run", ['fields' => ['topic' => 'otters']])
            ->streamedContent();

        // The visitor only received what was sent before they went away...
        $this->assertStringContainsString('Hel', $body);
        $this->assertStringNotContainsString('world', $body);

        // ...but the tokens the provider generated are charged all the same.
        $this->assertEquals(8.0, (float) $owner->fresh()->credits);

        $log = AiUsageLog::where('user_id', $owner->id)->latest('id')->first();
        $this->assertSame('cancelled', $log->status);
        $this->assertSame(2.0, (float) $log->credits_used);
        $this->assertTrue((bool) ($log->metadata['aborted'] ?? false));
    }
}
