<?php

namespace App\Services;

use App\Models\AiTool;
use App\Models\ToolChain;
use App\Models\ToolChainRun;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\AI\PromptBuilder;

class ToolChainService
{
    public function __construct(
        private readonly AiService $ai,
        private readonly PromptBuilder $promptBuilder,
    ) {}

    public function executeStep(array $step, string $previousOutput, array $initialInputs, User $user): array
    {
        $tool = AiTool::where('slug', $step['tool_slug'])->where('is_active', true)->firstOrFail();

        $inputs = $step['static_inputs'] ?? [];
        $fieldMap = $step['field_map'] ?? [];

        foreach ($fieldMap as $field => $template) {
            $resolved = $this->resolveTemplate($template, $step['step'], $previousOutput);
            $inputs[$field] = $resolved;
        }

        $result = $this->ai->runTemplate($user, $tool, $inputs);

        return [
            'output' => $result['content'] ?? '',
            'input_tokens' => $result['input_tokens'] ?? 0,
            'output_tokens' => $result['output_tokens'] ?? 0,
        ];
    }

    public function resolveTemplate(string $template, int $stepNumber, string $previousOutput): string
    {
        $resolved = str_replace('{{previous_output}}', $previousOutput, $template);

        return preg_replace_callback('/\{\{step_(\d+)_output\}\}/', function ($matches) use ($previousOutput) {
            return $previousOutput;
        }, $resolved);
    }
}
