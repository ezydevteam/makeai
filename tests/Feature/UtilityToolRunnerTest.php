<?php

namespace Tests\Feature;

use App\Models\AiTool;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\AI\UtilityToolRunner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Integration ↔ LLM fallback routing, billing, and model selection for the new
 * utility tools (Grammar as the reference engine).
 */
class UtilityToolRunnerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Credit deductions broadcast a balance event to Reverb/Pusher; mute it so
        // tests don't reach for localhost:8080.
        $this->instance(
            \App\Services\NotificationEventService::class,
            Mockery::mock(\App\Services\NotificationEventService::class)->shouldIgnoreMissing(),
        );

        // These assert METERED (wallet-drain) billing for the integration path.
        // A licence-less test env defaults to quota mode (allowance metering), so
        // enable metered mode (Extended license + billing) explicitly.
        settings_set('license_type', '2', 'integer', 'license');
        settings_set('subscriptions_enabled', '1', 'boolean', 'ai');
    }

    private function tool(string $mode, array $extra = []): AiTool
    {
        return AiTool::create(array_merge([
            'ulid' => (string) Str::ulid(),
            'name' => 'Grammar Checker',
            'slug' => 'grammar-checker-'.uniqid(),
            'type' => 'template',
            'generation_mode' => $mode,
            'integration_slug' => 'grammar',
            'prompt_system' => 'You are a grammar checker.',
            'prompt_user' => 'Check: {{text}}',
        ], $extra));
    }

    private function languageToolFake(): void
    {
        Http::fake([
            '*languagetool.org/*' => Http::response([
                'matches' => [[
                    'message' => 'Subject-verb agreement.',
                    'offset' => 2,
                    'length' => 3,
                    'replacements' => [['value' => 'have']],
                    'rule' => ['category' => ['name' => 'Grammar']],
                ]],
            ], 200),
        ]);
    }

    public function test_integration_path_runs_engine_and_charges_fixed_cost(): void
    {
        settings_set('external_grammar_enabled', true, 'boolean', 'external_apis');
        settings_set('external_grammar_fixed_credit_cost', '2', 'string', 'external_apis');
        $this->languageToolFake();

        $user = User::factory()->create(['credits' => 10, 'is_active' => true]);
        // AiService must NOT be touched on the integration path.
        $ai = Mockery::mock(AiService::class);
        $ai->shouldNotReceive('runTemplate');

        $result = (new UtilityToolRunner($ai))->run(
            $this->tool('integration_llm_fallback'),
            ['text' => 'I has a apple'],
            $user,
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('integration', $result['source']);
        $this->assertSame(1, $result['issue_count']);
        $this->assertStringContainsString('have', $result['corrected']);
        $this->assertSame(2.0, $result['credits_used']);
        $this->assertEquals(8.0, (float) $user->fresh()->credits, 'Fixed credit cost should be charged.');
    }

    public function test_falls_back_to_llm_when_integration_disabled(): void
    {
        settings_set('external_grammar_enabled', false, 'boolean', 'external_apis');
        $user = User::factory()->create(['credits' => 10, 'is_active' => true]);

        $ai = Mockery::mock(AiService::class);
        $ai->shouldReceive('runTemplate')->once()
            ->andReturn(['content' => 'LLM corrected text', 'model' => 'gpt-4o-mini', 'provider' => 'openai']);

        $result = (new UtilityToolRunner($ai))->run(
            $this->tool('integration_llm_fallback'),
            ['text' => 'I has a apple'],
            $user,
        );

        $this->assertSame('llm', $result['source']);
        $this->assertSame('LLM corrected text', $result['content']);
    }

    public function test_falls_back_to_llm_when_integration_errors(): void
    {
        settings_set('external_grammar_enabled', true, 'boolean', 'external_apis');
        Http::fake(['*languagetool.org/*' => Http::response('boom', 500)]);
        $user = User::factory()->create(['credits' => 10, 'is_active' => true]);

        $ai = Mockery::mock(AiService::class);
        $ai->shouldReceive('runTemplate')->once()
            ->andReturn(['content' => 'LLM fallback output', 'model' => 'gpt-4o-mini']);

        $result = (new UtilityToolRunner($ai))->run(
            $this->tool('integration_llm_fallback'),
            ['text' => 'x'],
            $user,
        );

        $this->assertSame('llm', $result['source']);
        $this->assertSame('LLM fallback output', $result['content']);
    }

    public function test_strict_integration_mode_errors_when_unavailable(): void
    {
        settings_set('external_grammar_enabled', false, 'boolean', 'external_apis');
        $user = User::factory()->create(['credits' => 10, 'is_active' => true]);

        $ai = Mockery::mock(AiService::class);
        $ai->shouldNotReceive('runTemplate');

        $result = (new UtilityToolRunner($ai))->run(
            $this->tool('integration'),
            ['text' => 'x'],
            $user,
        );

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('error', $result);
    }

    private function integrationTool(string $slug): AiTool
    {
        return AiTool::create([
            'ulid' => (string) Str::ulid(),
            'name' => ucfirst($slug),
            'slug' => $slug.'-'.uniqid(),
            'type' => 'template',
            'generation_mode' => 'integration_llm_fallback',
            'integration_slug' => $slug,
            'prompt_system' => 'x',
            'prompt_user' => '{{text}}',
        ]);
    }

    public function test_ai_detector_integration_returns_verdict(): void
    {
        settings_set('external_ai_detector_enabled', true, 'boolean', 'external_apis');
        settings_set('external_ai_detector_provider', 'gptzero', 'string', 'external_apis');
        settings_set('external_ai_detector_gptzero_api_key', 'k', 'encrypted', 'external_apis');
        Http::fake(['*gptzero.me/*' => Http::response(['documents' => [['class_probabilities' => ['ai' => 0.9]]]])]);

        $user = User::factory()->create(['credits' => 10, 'is_active' => true]);
        $ai = Mockery::mock(AiService::class);
        $ai->shouldNotReceive('runTemplate');

        $result = (new UtilityToolRunner($ai))->run($this->integrationTool('ai_detector'), ['text' => 'sample'], $user);

        $this->assertSame('integration', $result['source']);
        $this->assertSame('ai_detection', $result['type']);
        $this->assertSame(90.0, $result['ai_probability']);
        $this->assertSame('ai', $result['verdict']);
    }

    public function test_translation_integration_returns_translated_text(): void
    {
        settings_set('external_translation_enabled', true, 'boolean', 'external_apis');
        settings_set('external_translation_provider', 'deepl', 'string', 'external_apis');
        settings_set('external_translation_deepl_auth_key', 'key', 'encrypted', 'external_apis');
        Http::fake(['*deepl.com/*' => Http::response(['translations' => [['text' => 'Hallo', 'detected_source_language' => 'EN']]])]);

        $user = User::factory()->create(['credits' => 10, 'is_active' => true]);
        $ai = Mockery::mock(AiService::class);
        $ai->shouldNotReceive('runTemplate');

        $result = (new UtilityToolRunner($ai))->run(
            $this->integrationTool('translation'),
            ['text' => 'Hello', 'target_language' => 'DE'],
            $user,
        );

        $this->assertSame('integration', $result['source']);
        $this->assertSame('Hallo', $result['translated']);
        $this->assertSame('EN', $result['source_language']);
    }

    public function test_plagiarism_integration_returns_percent(): void
    {
        settings_set('external_plagiarism_enabled', true, 'boolean', 'external_apis');
        settings_set('external_plagiarism_provider', 'originality', 'string', 'external_apis');
        settings_set('external_plagiarism_originality_api_key', 'k', 'encrypted', 'external_apis');
        Http::fake(['*originality.ai/*' => Http::response([
            'total_text_score' => 0.4,
            'results' => ['matches' => [['url' => 'https://x.test', 'score' => 40]]],
        ])]);

        $user = User::factory()->create(['credits' => 10, 'is_active' => true]);
        $ai = Mockery::mock(AiService::class);
        $ai->shouldNotReceive('runTemplate');

        $result = (new UtilityToolRunner($ai))->run($this->integrationTool('plagiarism'), ['text' => 'sample'], $user);

        $this->assertSame('integration', $result['source']);
        $this->assertSame('plagiarism', $result['type']);
        $this->assertSame(40.0, $result['plagiarism_percent']);
        $this->assertSame(1, $result['match_count']);
    }

    public function test_fallback_model_prefers_override_then_default(): void
    {
        settings_set('default_ai_model', 'gpt-4o-mini', 'string', 'ai');

        $withOverride = $this->tool('integration_llm_fallback', ['model_override' => 'gpt-4o']);
        $withoutOverride = $this->tool('integration_llm_fallback');

        $this->assertSame('gpt-4o', $withOverride->fallbackModel());
        $this->assertSame('gpt-4o-mini', $withoutOverride->fallbackModel());
    }
}
