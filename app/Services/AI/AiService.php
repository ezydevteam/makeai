<?php

namespace App\Services\AI;

use App\Models\AiChat;
use App\Models\AiChatMessage;
use App\Models\AiTemplate;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

/**
 * AiService — the main entry point for all AI operations.
 *
 * CAVEMAN say: "One service to make fire! 🔥"
 * Controllers call this. This calls TokenGuard → Provider → UsageLog.
 *
 * Ref: AI_SaaS_Master_Prompt Part 11
 */
class AiService
{
    private PromptBuilder $promptBuilder;

    public function __construct(PromptBuilder $promptBuilder)
    {
        $this->promptBuilder = $promptBuilder;
    }

    /**
     * Run a chat completion (stateless — no history).
     */
    public function complete(
        User $user,
        string $prompt,
        ?string $systemPrompt = null,
        ?string $provider = null,
        ?string $model = null,
        array $options = []
    ): array {
        // Resolve provider + model
        $providerName = $provider ?? settings('default_ai_provider', 'openai');
        $modelName = $model ?? settings('default_ai_model', 'gpt-4o-mini');
        $adapter = ProviderRegistry::resolve($providerName);

        try {
            // TokenGuard pre-flight check
            TokenGuard::before($user, null, $modelName);
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $modelName, 'chat', 0, 0, [
                'preflight_error' => class_basename($e),
            ]);

            throw $e;
        }

        // Build messages
        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        try {
            // Call provider
            $result = $adapter->chatCompletion($messages, $modelName, $options);

            // TokenGuard post-completion
            TokenGuard::after(
                $user,
                $result['input_tokens'],
                $result['output_tokens'],
                $result['model'],
                $providerName,
                'chat'
            );
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $modelName, 'chat', 0, 0, [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $result;
    }

    /**
     * Run a template-based completion using the PromptBuilder.
     */
    public function runTemplate(
        User $user,
        AiTemplate $template,
        array $inputs,
        ?string $provider = null,
        ?string $model = null
    ): array {
        // Use PromptBuilder for proper prompt assembly
        $completion = $this->promptBuilder->build($template, $inputs, $user);

        // Override model if explicitly provided
        $finalModel = $model ?? $completion->model;
        $providerName = $provider ?? $completion->provider ?? settings('default_ai_provider', 'openai');

        try {
            // TokenGuard pre-flight check
            TokenGuard::before($user, $template, $finalModel);
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $finalModel, 'template', 0, 0, [
                'template_slug' => $template->slug,
                'preflight_error' => class_basename($e),
            ]);

            throw $e;
        }

        // Resolve provider with appropriate API key
        $adapter = $completion->apiKey
            ? ProviderRegistry::resolveWithKey($providerName, $completion->apiKey)
            : ProviderRegistry::resolve($providerName);

        try {
            // Call provider
            $result = $adapter->chatCompletion(
                $completion->toMessages(),
                $finalModel,
                $completion->toOptions()
            );

            // TokenGuard post-completion
            TokenGuard::after(
                $user,
                $result['input_tokens'],
                $result['output_tokens'],
                $result['model'],
                $providerName,
                'template',
                ['template_slug' => $template->slug]
            );
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $finalModel, 'template', 0, 0, [
                'template_slug' => $template->slug,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $template->incrementUsage();

        return array_merge($result, [
            'output_type' => $template->output_type ?? 'markdown',
        ]);
    }

    /**
     * Send a message in a chat conversation.
     */
    public function chat(
        User $user,
        AiChat $chat,
        string $message,
        ?string $provider = null,
        ?string $model = null
    ): AiChatMessage {
        $providerName = $provider ?? settings('default_ai_provider', 'openai');
        $modelName = $model ?? $chat->model ?? settings('default_ai_model', 'gpt-4o-mini');

        try {
            // TokenGuard pre-flight check
            TokenGuard::before($user, null, $modelName);
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $modelName, 'chat', 0, 0, [
                'chat_id' => $chat->id,
                'preflight_error' => class_basename($e),
            ]);

            throw $e;
        }

        $adapter = ProviderRegistry::resolve($providerName);

        // Save user message
        $userMessage = $chat->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);

        // Get conversation history
        $messages = $chat->getMessagesForApi();

        try {
            // Call provider
            $result = $adapter->chatCompletion($messages, $modelName);

            // Save assistant response
            $assistantMessage = $chat->messages()->create([
                'role' => 'assistant',
                'content' => $result['content'],
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
                'metadata' => ['model' => $result['model'], 'provider' => $providerName],
            ]);

            // TokenGuard post-completion
            TokenGuard::after(
                $user,
                $result['input_tokens'],
                $result['output_tokens'],
                $result['model'],
                $providerName,
                'chat',
                ['chat_id' => $chat->id]
            );
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $modelName, 'chat', 0, 0, [
                'chat_id' => $chat->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        // Update chat title if it's the first message
        if ($chat->messages()->count() <= 2 && $chat->title === 'New Chat') {
            $chat->update(['title' => Str::limit($message, 60)]);
        }

        // Update model on chat
        if ($chat->model !== $modelName) {
            $chat->update(['model' => $modelName]);
        }

        return $assistantMessage;
    }

    /**
     * Stream a template-based completion.
     *
     * Returns a Generator that yields tokens.
     */
    public function streamTemplate(
        User $user,
        AiTemplate $template,
        array $inputs,
        ?string $provider = null,
        ?string $model = null
    ): \Generator {
        // Use PromptBuilder
        $completion = $this->promptBuilder->build($template, $inputs, $user);
        $finalModel = $model ?? $completion->model;
        $providerName = $provider ?? $completion->provider ?? settings('default_ai_provider', 'openai');

        try {
            // TokenGuard pre-flight check
            TokenGuard::before($user, $template, $finalModel);
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $finalModel, 'template', 0, 0, [
                'template_slug' => $template->slug,
                'preflight_error' => class_basename($e),
            ]);

            throw $e;
        }

        // Resolve provider
        $adapter = $completion->apiKey
            ? ProviderRegistry::resolveWithKey($providerName, $completion->apiKey)
            : ProviderRegistry::resolve($providerName);

        try {
            // Stream
            $usageStats = null;
            foreach ($adapter->streamChatCompletion($completion->toMessages(), $finalModel, $completion->toOptions()) as $chunk) {
                if (is_string($chunk)) {
                    yield $chunk;
                } elseif (is_array($chunk)) {
                    $usageStats = $chunk;
                }
            }

            // Record usage after stream completes
            if ($usageStats) {
                $creditsUsed = TokenGuard::after(
                    $user,
                    $usageStats['input_tokens'],
                    $usageStats['output_tokens'],
                    $usageStats['model'] ?? $finalModel,
                    $providerName,
                    'template',
                    ['template_slug' => $template->slug]
                );

                $template->incrementUsage();

                yield [
                    'usage' => [
                        'input_tokens' => $usageStats['input_tokens'],
                        'output_tokens' => $usageStats['output_tokens'],
                        'credits_used' => $creditsUsed,
                    ],
                ];
            }
        } catch (Throwable $e) {
            TokenGuard::recordFailure($user, $providerName, $finalModel, 'template', 0, 0, [
                'template_slug' => $template->slug,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
