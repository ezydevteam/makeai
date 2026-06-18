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

        // 4. Resolve model — respects default_ai_provider setting
        $model = $template->model_override
            ?? ($fields['model'] ?? null)
            ?? $this->resolveDefaultModel();

        // 5. Resolve API key (user personal → admin pool)
        $apiKey = $this->resolveApiKey($model, $user);

        // 6. Max tokens
        $maxTokens = $template->max_tokens_override
            ?? (int) settings('default_max_tokens', 2000);

        // 3. Apply dynamic length instruction based on max tokens
        $system .= $this->getLengthInstruction($fields['length'] ?? 'medium', $maxTokens);

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
     * Unresolved placeholders are replaced with a visible marker to aid debugging.
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

        // Replace unresolved placeholders with visible markers instead of silently removing them
        // This helps admins identify missing field configurations
        $prompt = preg_replace_callback('/\{\{([a-z_]+)\}\}/i', function ($matches) {
            \Log::warning('PromptBuilder: unresolved placeholder', ['key' => $matches[1]]);
            return "[MISSING: {$matches[1]}]";
        }, $prompt);

        $prompt = preg_replace_callback('/\{([a-z_]+)\}/i', function ($matches) {
            \Log::warning('PromptBuilder: unresolved placeholder', ['key' => $matches[1]]);
            return "[MISSING: {$matches[1]}]";
        }, $prompt);

        return trim($prompt);
    }

    /**
     * Map length setting to dynamic token instruction based on max tokens.
     */
    private function getLengthInstruction(string $length, int $maxTokens): string
    {
        $words = match (strtolower($length)) {
            'short' => max(10, (int) round(($maxTokens * 0.07) / 1.3, -1)),
            'medium' => max(10, (int) round(($maxTokens * 0.20) / 1.3, -1)),
            'long' => max(10, (int) round(($maxTokens * 0.40) / 1.3, -1)),
            'very_long', 'very long' => max(10, (int) round(($maxTokens * 0.80) / 1.3, -1)),
            default => max(10, (int) round(($maxTokens * 0.20) / 1.3, -1)),
        };
        
        return "\nOutput length: approximately {$words} words.";
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
     * Resolve the default model slug, ensuring it matches the admin's default_ai_provider.
     *
     * Handles the case where admin changed default_ai_provider (e.g. to 'google')
     * but default_ai_model still points to a different provider's model (e.g. 'gpt-4o-mini').
     */
    private function resolveDefaultModel(): string
    {
        $defaultModel = settings('default_ai_model', 'gpt-4o-mini');
        $defaultProvider = settings('default_ai_provider', 'openai');

        // Check if the stored default model actually belongs to the default provider
        $resolvedProvider = $this->resolveProvider($defaultModel);

        if ($resolvedProvider === $defaultProvider) {
            return $defaultModel;
        }

        // Provider/model mismatch — pick the first active model for the correct provider
        $providerModel = AiModel::where('provider', $defaultProvider)
            ->where('is_active', true)
            ->orderBy('slug')
            ->first();

        if ($providerModel) {
            return $providerModel->slug;
        }

        // No models found for the provider — fall back to stored model
        return $defaultModel;
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
    public function estimateCost(AiTool $template, string $model, ?string $outputLength = null): array
    {
        $estimatedTokens = $template->avg_output_tokens ?? 400;

        // Apply output length multiplier
        if ($outputLength) {
            $multipliers = ['short' => 0.5, 'medium' => 1, 'long' => 2, 'very_long' => 4];
            $estimatedTokens = (int) round($estimatedTokens * ($multipliers[$outputLength] ?? 1));
        }

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

    /**
     * Build a CompletionRequest for refining/improving existing content.
     */
    public function buildRefine(AiTool $template, string $content, string $instruction, ?string $model, ?User $user): CompletionRequest
    {
        $system = "You are an AI assistant designed to refine and improve text. Rewrite the provided content following the user's instructions. Maintain the same formatting (markdown, code, etc.) where appropriate. Return ONLY the refined content, no preamble.";
        $userMessage = "Content to refine:\n\n" . $content . "\n\nInstruction: " . $instruction;

        $model = $model
            ?? $template->model_override
            ?? $this->resolveDefaultModel();

        $apiKey = $this->resolveApiKey($model, $user);

        $maxTokens = $template->max_tokens_override
            ?? (int) settings('default_max_tokens', 2000);

        return new CompletionRequest(
            model: $model,
            systemPrompt: $system,
            userMessage: $userMessage,
            maxTokens: $maxTokens,
            temperature: 0.6,
            apiKey: $apiKey,
            provider: $this->resolveProvider($model),
        );
    }
}
