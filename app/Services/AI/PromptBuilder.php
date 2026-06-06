<?php

namespace App\Services\AI;

use App\DTO\CompletionRequest;
use App\Models\AiModel;
use App\Models\AiTool;
use App\Models\User;

/**
 * PromptBuilder — assembles final prompts from template + user fields.
 *
 * Ref: AI_SaaS_Master_Prompt Part 15.6
 *
 * Responsibilities:
 * 1. Interpolate {field_name} placeholders in system & user prompts
 * 2. Inject brand voice when supported
 * 3. Append length instruction
 * 4. Resolve model (template override → user choice → global default)
 * 5. Resolve API key (user personal → admin pool)
 */
class PromptBuilder
{
    /**
     * Build a CompletionRequest from template + user inputs.
     */
    public function build(AiTool $template, array $fields, ?User $user): CompletionRequest
    {
        // 1. Replace {field_name} in prompt_system and prompt_user
        $systemPrompt = $this->getSystemPrompt($template);
        $userPrompt = $this->getUserPrompt($template);

        $system = $this->interpolate($systemPrompt, $fields);
        $userMessage = $this->interpolate($userPrompt, $fields);

        // 2. Inject brand voice if user has one AND template supports it
        if ($user && ! empty($user->brand_voice) && $template->supports_brand_voice) {
            $system .= "\n\nBrand voice context:\n".$user->brand_voice;
        }

        // 3. Apply length instruction
        $system .= $this->getLengthInstruction($fields['length'] ?? 'medium');

        // 4. Resolve model
        $model = $template->model_override
            ?? ($fields['model'] ?? null)
            ?? settings('default_ai_model', 'gpt-4o-mini');

        // 5. Resolve API key (user personal → admin pool)
        $apiKey = $this->resolveApiKey($model, $user);

        // 6. Max tokens
        $maxTokens = $template->max_tokens_override
            ?? (int) settings('default_max_tokens', 2000);

        // 7. Temperature
        $temperature = isset($fields['creativity'])
            ? (float) $fields['creativity']
            : (float) ($template->temperature ?? 0.7);

        return new CompletionRequest(
            model: $model,
            systemPrompt: $system,
            userMessage: $userMessage,
            maxTokens: $maxTokens,
            temperature: $temperature,
            apiKey: $apiKey,
            provider: $this->resolveProvider($model),
        );
    }

    /**
     * Get the system prompt, falling back to legacy 'prompt' column.
     */
    private function getSystemPrompt(AiTool $template): string
    {
        return $template->prompt_system ?? $template->prompt ?? '';
    }

    /**
     * Get the user prompt template.
     */
    private function getUserPrompt(AiTool $template): string
    {
        return $template->prompt_user ?? '';
    }

    /**
     * Replace {field_name} placeholders in a prompt string.
     */
    private function interpolate(string $prompt, array $fields): string
    {
        foreach ($fields as $key => $value) {
            // Handle arrays (e.g. tags_input → comma-separated)
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            // Replace both {key} and {{key}} patterns
            $prompt = str_replace('{'.$key.'}', (string) $value, $prompt);
            $prompt = str_replace('{{'.$key.'}}', (string) $value, $prompt);
        }

        // Clean up any remaining unresolved placeholders
        $prompt = preg_replace('/\{[a-z_]+\}/i', '', $prompt);
        $prompt = preg_replace('/\{\{[a-z_]+\}\}/i', '', $prompt);

        return trim($prompt);
    }

    /**
     * Map length setting to token instruction.
     */
    private function getLengthInstruction(string $length): string
    {
        return match (strtolower($length)) {
            'short' => "\nOutput length: approximately 100 words.",
            'medium' => "\nOutput length: approximately 300 words.",
            'long' => "\nOutput length: approximately 600 words.",
            'very_long', 'very long' => "\nOutput length: approximately 1200 words.",
            default => '',
        };
    }

    /**
     * Resolve API key — user personal key takes priority over admin pool.
     */
    private function resolveApiKey(string $model, ?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $provider = $this->resolveProvider($model);

        $key = $user->apiKeys()->where('provider', $provider)->active()->first();
        if ($key) {
            return $key->api_key;
        }

        return null;
    }

    /**
     * Determine provider from model slug.
     */
    private function resolveProvider(string $model): string
    {
        // Check ai_models table first
        $dbModel = AiModel::where('slug', $model)->first();
        if ($dbModel) {
            return $dbModel->provider;
        }

        // Fallback: infer from model name prefix
        $model = strtolower($model);

        return match (true) {
            str_starts_with($model, 'gpt-') => 'openai',
            str_starts_with($model, 'o1') => 'openai',
            str_starts_with($model, 'o3') => 'openai',
            str_starts_with($model, 'o4') => 'openai',
            str_starts_with($model, 'claude') => 'anthropic',
            str_starts_with($model, 'gemini') => 'google',
            str_starts_with($model, 'deepseek') => 'deepseek',
            str_starts_with($model, 'grok') => 'xai',
            default => settings('default_ai_provider', 'openai'),
        };
    }

    /**
     * Estimate credit cost before generation.
     */
    public function estimateCost(AiTool $template, string $model): array
    {
        $estimatedTokens = $template->avg_output_tokens ?? 400;
        $dbModel = AiModel::where('slug', $model)->first();

        if (! $dbModel) {
            return [
                'estimated_credits' => round($estimatedTokens / 1000, 2),
                'estimated_tokens' => $estimatedTokens,
            ];
        }

        $outputCost = ($estimatedTokens / 1000) * (float) $dbModel->cost_output_1k;
        $inputCost = (200 / 1000) * (float) $dbModel->cost_input_1k; // estimate ~200 input tokens
        $totalUsd = $inputCost + $outputCost;
        $credits = $totalUsd * (float) settings('credits_per_usd', 100);

        return [
            'estimated_credits' => round($credits, 2),
            'estimated_tokens' => $estimatedTokens,
            'estimated_usd' => round($totalUsd, 6),
        ];
    }
}
