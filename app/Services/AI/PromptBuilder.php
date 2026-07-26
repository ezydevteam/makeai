<?php

namespace App\Services\AI;

use App\DTO\CompletionRequest;
use App\Models\AiModel;
use App\Models\AiTool;
use App\Models\User;

/**
 * PromptBuilder — assembles final prompts from template + user fields.
 *
 * Responsibilities:
 * 1. Interpolate {field_name} placeholders in system & user prompts
 * 2. Inject brand voice when supported
 * 3. Append length instruction
 * 4. Resolve model (user choice → template override → global default)
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

        // Expose tool metadata to the templates. The shared user prompt uses
        // {name}, which is not a form field, so without this every tool rendered
        // "Tool: [MISSING: name]". User-supplied fields still win on any clash.
        $fields = array_merge(['name' => $template->name], $fields);

        $system = $this->interpolate($systemPrompt, $fields);
        // The user prompt lists optional fields (audience, tone, keywords, …) that
        // most tools do not collect. Interpolation leaves those as empty values;
        // strip the resulting bare "Label:" lines so they never reach the model.
        $userMessage = $this->stripEmptyLabelLines($this->interpolate($userPrompt, $fields));

        // 2. Inject brand voice if the user has one, the template supports it, and
        // the user did not switch it off for this generation. The flag is absent on
        // API/embed calls, which must keep the prior always-on behaviour, so it
        // defaults to true.
        $useBrandVoice = ! array_key_exists('use_brand_voice', $fields)
            || filter_var($fields['use_brand_voice'], FILTER_VALIDATE_BOOLEAN);

        if ($useBrandVoice && $user && ! empty($user->brand_voice) && $template->supports_brand_voice) {
            $system .= "\n\nBrand voice context:\n".$user->brand_voice;
        }

        // 4. Resolve model — the user's explicit pick wins, then the tool's override,
        // then the global default. The caller has already validated that a supplied
        // model is one the admin activated. This order is what estimateCost(),
        // buildRefine() and the CheckCredits pre-flight all assume; putting the tool
        // override first made the quoted cost disagree with the model actually used.
        $model = ($fields['model'] ?? null)
            ?: ($template->model_override ?: $this->resolveDefaultModel());

        // 5. Resolve API key (user personal → admin pool)
        $apiKey = $this->resolveApiKey($model, $user);

        // 6. Max tokens — tool override or global default, then clamped to the
        // model's own output ceiling (admin-editable per-model Max Tokens; 0 = no cap).
        $maxTokens = $template->max_tokens_override
            ?? (int) settings('default_max_tokens', 2000);
        $maxTokens = $this->clampToModelMaxTokens($maxTokens, $model);

        // 3. Apply dynamic length instruction based on max tokens
        $system .= $this->getLengthInstruction($fields['length'] ?? 'medium', $maxTokens);

        // 7. Temperature — clamp user-supplied creativity to a valid range
        $temperature = isset($fields['creativity']) && is_numeric($fields['creativity'])
            ? (float) $fields['creativity']
            : (float) ($template->temperature ?? 0.7);
        $temperature = max(0.0, min(2.0, $temperature));

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
        $replacements = [];
        foreach ($fields as $key => $value) {
            // Handle arrays (e.g. tags_input → comma-separated)
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $replacements['{{'.$key.'}}'] = (string) $value;
            $replacements['{'.$key.'}'] = (string) $value;
        }

        // Mark unresolved placeholders BEFORE substituting, so braces inside
        // user-provided values are never rewritten. Visible markers help
        // admins spot missing field configurations.
        $prompt = preg_replace_callback('/\{\{([a-z0-9_]+)\}\}|\{([a-z0-9_]+)\}/i', function ($matches) use ($replacements) {
            if (array_key_exists($matches[0], $replacements)) {
                return $matches[0];
            }

            $key = ($matches[1] ?? '') !== '' ? $matches[1] : ($matches[2] ?? '');

            // A placeholder the tool does not collect is expected, not a
            // misconfiguration: the shared prompt template lists optional fields
            // many tools omit. In production it renders EMPTY, so no "[MISSING: …]"
            // debug marker (and no per-generation log spam) ever reaches the
            // model. Only in debug is it surfaced to the author as an aid.
            if (config('app.debug')) {
                \Log::warning('PromptBuilder: unresolved placeholder', ['key' => $key]);

                return "[MISSING: {$key}]";
            }

            return '';
        }, $prompt);

        // Single-pass strtr ({{key}} wins over {key}); substituted values are
        // never re-scanned, so a value containing "{other_field}" cannot
        // expand into another field's value.
        return trim(strtr($prompt, $replacements));
    }

    /**
     * Drop "Label:" lines whose value interpolated to empty, and collapse the
     * blank space left behind.
     *
     * The shared user-prompt template lists every optional field (audience, tone,
     * keywords, length, …); a tool that does not collect one leaves a bare
     * "Tone:" line, which wastes tokens and reads to the model as missing data.
     * Only applied to the USER prompt — the system prompt has real "Rules:"
     * headers this would otherwise eat.
     */
    private function stripEmptyLabelLines(string $prompt): string
    {
        $lines = preg_split('/\r?\n/', $prompt) ?: [];

        $kept = array_filter($lines, static function (string $line): bool {
            // A short label (no colon of its own) followed by ": " and nothing.
            return ! preg_match('/^[^:\n]{1,60}:[ \t]*$/', $line);
        });

        $out = preg_replace("/\n{3,}/", "\n\n", implode("\n", $kept)) ?? implode("\n", $kept);

        return trim($out);
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

        // BYOK disabled by the admin — never fall back to a user's saved key, or a
        // previously-stored key would keep bypassing platform billing after the
        // feature is turned off.
        if (! (bool) settings('byok_enabled', true)) {
            return null;
        }

        $provider = $this->resolveProvider($model);

        $key = $user->byok()->where('provider', $provider)->active()->first();
        if ($key) {
            return $key->api_key;
        }

        return null;
    }

    /**
     * Resolve the default model slug, ensuring it matches the admin's default_ai_provider.
     *
     * Handles the case where admin changed default_ai_provider (e.g. to 'google')
     * but default_ai_model still points to a different provider's model (e.g. 'gpt-5.4-mini').
     */
    private function resolveDefaultModel(): string
    {
        $defaultModel = settings('default_ai_model', config('ai.fallback_model'));
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
     *
     * Uses the same credits_per_1k formula as TokenGuard so the estimate
     * shown to the user matches what is actually deducted.
     */
    public function estimateCost(AiTool $template, string $model, ?string $outputLength = null, ?User $user = null): array
    {
        $estimatedTokens = $template->avg_output_tokens ?? 400;

        // Apply output length multiplier
        if ($outputLength) {
            $multipliers = ['short' => 0.5, 'medium' => 1, 'long' => 2, 'very_long' => 4];
            $estimatedTokens = (int) round($estimatedTokens * ($multipliers[$outputLength] ?? 1));
        }

        $estimatedInputTokens = 200; // rough prompt overhead
        $totalTokens = $estimatedInputTokens + $estimatedTokens;

        $dbModel = AiModel::resolveForPricing($model);

        if (! $dbModel) {
            $estimate = [
                'estimated_credits' => round($totalTokens / 1000, 2),
                'estimated_tokens' => $estimatedTokens,
            ];
        } else {
            // Mirror TokenGuard::calculateCredits — credits_per_1k across all tokens
            $credits = round($totalTokens * ((float) $dbModel->credits_per_1k / 1000), 2);

            $totalUsd = ($estimatedInputTokens / 1000) * (float) $dbModel->cost_input_1k
                + ($estimatedTokens / 1000) * (float) $dbModel->cost_output_1k;

            $estimate = [
                'estimated_credits' => $credits,
                'estimated_tokens' => $estimatedTokens,
                'estimated_usd' => round($totalUsd, 6),
            ];
        }

        // A user generating with their own API key (BYOK) is never charged platform
        // credits (TokenGuard::after deducts only when there is no personal key), so the
        // estimate must show $0 rather than overstating a cost that never lands.
        if ($user && $this->resolveApiKey($model, $user) !== null) {
            $estimate['estimated_credits'] = 0.0;
            $estimate['byok'] = true;
        }

        return $estimate;
    }

    /**
     * Clamp a desired output-token budget to the model's own Max Tokens ceiling.
     *
     * The per-model Max Tokens (admin-editable) is a hard upper bound on output;
     * a tool override or the global default must never exceed it. A model row that
     * is unknown, or configured with max_tokens = 0 (e.g. media/embedding), imposes
     * no cap.
     */
    private function clampToModelMaxTokens(int $maxTokens, string $model): int
    {
        $dbModel = AiModel::resolveForPricing($model);

        if ($dbModel && (int) $dbModel->max_tokens > 0) {
            return min($maxTokens, (int) $dbModel->max_tokens);
        }

        return $maxTokens;
    }

    /**
     * Build a CompletionRequest for refining/improving existing content.
     */
    public function buildRefine(AiTool $template, string $content, string $instruction, ?string $model, ?User $user): CompletionRequest
    {
        $system = "You are an AI assistant designed to refine and improve text. Rewrite the provided content following the user's instructions. Maintain the same formatting (markdown, code, etc.) where appropriate. Return ONLY the refined content, no preamble.";
        $userMessage = "Content to refine:\n\n" . $content . "\n\nInstruction: " . $instruction;

        // `?:` not `??` — the caller passes '' (not null) when the tool has no model
        // picker, and an empty slug would reach the provider as a blank model name.
        $model = $model
            ?: ($template->model_override ?: $this->resolveDefaultModel());

        $apiKey = $this->resolveApiKey($model, $user);

        $maxTokens = $template->max_tokens_override
            ?? (int) settings('default_max_tokens', 2000);
        $maxTokens = $this->clampToModelMaxTokens($maxTokens, $model);

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
