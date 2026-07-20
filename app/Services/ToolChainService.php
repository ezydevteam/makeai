<?php

namespace App\Services;

use App\Models\AiTool;
use App\Models\User;
use App\Services\AI\AiService;

class ToolChainService
{
    public function __construct(
        private readonly AiService $ai,
    ) {}

    /**
     * Run one step of a chain.
     *
     * A step's inputs come from two places: `static_inputs` (fixed values the user
     * typed when building the chain) and `field_map` (values templated from the
     * chain's input or an earlier step's output). Without the second, a step runs
     * the tool with no fields at all and PromptBuilder emits [MISSING: …] markers.
     *
     * @param  array<int, string>  $stepOutputs  outputs of completed steps, keyed by step number
     */
    public function executeStep(array $step, array $stepOutputs, string $initialInput, User $user): array
    {
        $tool = AiTool::where('slug', $step['tool_slug'])->where('is_active', true)->firstOrFail();

        $inputs = $step['static_inputs'] ?? [];

        foreach (($step['field_map'] ?? []) as $field => $template) {
            $inputs[$field] = $this->resolveTemplate((string) $template, $stepOutputs, $initialInput);
        }

        $result = $this->ai->runTemplate($user, $tool, $inputs);

        return [
            'output' => $result['content'] ?? '',
            'input_tokens' => (int) ($result['input_tokens'] ?? 0),
            'output_tokens' => (int) ($result['output_tokens'] ?? 0),
            'credits_used' => (float) ($result['credits_used'] ?? 0),
        ];
    }

    /**
     * Resolve the template tokens a chain step may reference:
     *   {{input}}           — the text the user supplied when running the chain
     *   {{previous_output}} — output of the step immediately before this one
     *   {{step_N_output}}   — output of step N specifically
     *
     * @param  array<int, string>  $stepOutputs  keyed by step number (1-based)
     */
    public function resolveTemplate(string $template, array $stepOutputs, string $initialInput = ''): string
    {
        $previousOutput = $stepOutputs === [] ? '' : (string) end($stepOutputs);

        $resolved = strtr($template, [
            '{{input}}' => $initialInput,
            '{{previous_output}}' => $previousOutput,
        ]);

        // Resolve the referenced step, not whatever ran last: the old callback
        // discarded the captured number and returned the previous output for every N.
        return preg_replace_callback('/\{\{step_(\d+)_output\}\}/', function (array $matches) use ($stepOutputs) {
            return (string) ($stepOutputs[(int) $matches[1]] ?? '');
        }, $resolved);
    }
}
