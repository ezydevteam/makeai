<?php

namespace Tests\Unit;

use App\Models\AiModel;
use App\Models\AiTool;
use App\Services\AI\PromptBuilder;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Finding #1 — the per-model Max Tokens (admin-editable) must act as a hard
 * output ceiling. A tool's max_tokens_override or the global default is clamped
 * down to the model's max_tokens; a model with max_tokens = 0 imposes no cap.
 */
class PromptBuilderMaxTokensTest extends TestCase
{
    private function tool(string $model, int $override): AiTool
    {
        return AiTool::create([
            'ulid' => (string) Str::ulid(),
            'name' => 'Clamp Tool',
            'slug' => 'clamp-'.uniqid(),
            'type' => 'tool',
            'model_override' => $model,
            'max_tokens_override' => $override,
        ]);
    }

    private function model(string $slug, int $maxTokens): AiModel
    {
        return AiModel::create([
            'slug' => $slug,
            'name' => $slug,
            'provider' => 'openai',
            'type' => 'chat',
            'is_active' => true,
            'cost_input_1k' => 0.001,
            'cost_output_1k' => 0.002,
            'credits_per_1k' => 1,
            'credits_auto' => true,
            'max_tokens' => $maxTokens,
        ]);
    }

    public function test_clamps_override_down_to_model_ceiling(): void
    {
        $this->model('clamp-model', 100);
        $tool = $this->tool('clamp-model', 5000);

        $request = (new PromptBuilder)->build($tool, [], null);

        $this->assertSame(100, $request->maxTokens);
    }

    public function test_override_below_ceiling_is_left_untouched(): void
    {
        $this->model('roomy-model', 16384);
        $tool = $this->tool('roomy-model', 2000);

        $request = (new PromptBuilder)->build($tool, [], null);

        $this->assertSame(2000, $request->maxTokens);
    }

    public function test_zero_model_max_tokens_imposes_no_cap(): void
    {
        $this->model('uncapped-model', 0);
        $tool = $this->tool('uncapped-model', 5000);

        $request = (new PromptBuilder)->build($tool, [], null);

        $this->assertSame(5000, $request->maxTokens);
    }
}
