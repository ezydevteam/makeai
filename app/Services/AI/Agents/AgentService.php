<?php

namespace App\Services\AI\Agents;

use App\Services\AI\AiService;
use App\Services\AI\ProviderRegistry;
use RuntimeException;

/**
 * AgentService — executes AI agents with tool calling capability.
 *
 * Agents are registered classes that implement the Agent contract.
 * They can use tools (functions) that the AI can call during execution.
 */
class AgentService
{
    /**
     * Registered agent definitions.
     *
     * @var array<string, array{name: string, instructions: string, tools: array}>
     */
    private static array $agents = [];

    public function __construct(
        private AiService $aiService,
    ) {}

    /**
     * Register an agent definition.
     */
    public static function register(string $name, string $instructions, array $tools = []): void
    {
        self::$agents[$name] = [
            'name' => $name,
            'instructions' => $instructions,
            'tools' => $tools,
        ];
    }

    /**
     * Run an agent with a user message.
     */
    public function run(
        string $agentName,
        string $userMessage,
        array $tools = [],
        ?string $provider = null,
        ?string $model = null,
    ): array {
        $agent = self::$agents[$agentName] ?? null;

        if (! $agent) {
            throw new RuntimeException("Agent '{$agentName}' is not registered. Register it first with AgentService::register().");
        }

        $allTools = array_merge($agent['tools'], $tools);

        $providerName = $provider ?? settings('default_ai_provider', 'openai');
        $modelName = $model ?? settings('default_ai_model', 'gpt-4o-mini');

        $adapter = ProviderRegistry::resolve($providerName);

        // Build tool definitions for the prompt
        $toolDefinitions = $this->buildToolDefinitions($allTools);

        // Build system prompt with tool instructions
        $systemPrompt = $agent['instructions'];

        if (! empty($toolDefinitions)) {
            $systemPrompt .= "\n\nYou have access to the following tools. When you need to use a tool, respond with a JSON object containing 'tool' and 'arguments' keys.\n\n";
            $systemPrompt .= json_encode($toolDefinitions, JSON_PRETTY_PRINT);
            $systemPrompt .= "\n\nAlways respond in valid JSON only.";
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userMessage],
        ];

        // Execute with potential tool calling loop (max 5 iterations)
        $maxIterations = 5;
        $iteration = 0;
        $toolResults = [];

        while ($iteration < $maxIterations) {
            $iteration++;

            $result = $adapter->chatCompletion(
                $messages,
                $modelName,
                ['temperature' => 0.7]
            );

            $content = $result['content'];

            // Check if the response is a tool call
            $toolCall = $this->parseToolCall($content);

            if ($toolCall === null) {
                // No tool call — agent is done
                return [
                    'response' => $content,
                    'tool_results' => $toolResults,
                    'iterations' => $iteration,
                    'model' => $result['model'],
                    'input_tokens' => $result['input_tokens'],
                    'output_tokens' => $result['output_tokens'],
                ];
            }

            // Execute tool
            $toolName = $toolCall['tool'];
            $toolArgs = $toolCall['arguments'] ?? [];

            $toolResponse = $this->executeTool($toolName, $toolArgs, $allTools);
            $toolResults[] = [
                'tool' => $toolName,
                'arguments' => $toolArgs,
                'result' => $toolResponse,
            ];

            // Add tool result to conversation
            $messages[] = ['role' => 'assistant', 'content' => $content];
            $messages[] = [
                'role' => 'user',
                'content' => "Tool '{$toolName}' returned: ".json_encode($toolResponse),
            ];
        }

        // Max iterations reached — return last response
        $finalResult = $adapter->chatCompletion($messages, $modelName);

        return [
            'response' => $finalResult['content'],
            'tool_results' => $toolResults,
            'iterations' => $iteration,
            'warning' => 'Max agent iterations reached.',
            'model' => $finalResult['model'],
        ];
    }

    /**
     * Build tool definitions for the system prompt.
     */
    private function buildToolDefinitions(array $tools): array
    {
        $definitions = [];

        foreach ($tools as $tool) {
            if (is_callable($tool)) {
                // Reflect on the callable to build a definition
                $ref = new \ReflectionFunction($tool);
                $params = [];
                foreach ($ref->getParameters() as $param) {
                    $params[$param->getName()] = [
                        'type' => (string) ($param->getType() ?? 'string'),
                        'required' => ! $param->isOptional(),
                    ];
                }

                $definitions[] = [
                    'name' => $ref->getName() ?: 'anonymous_tool',
                    'description' => 'Execute a function with the given arguments.',
                    'parameters' => $params,
                ];
            } elseif (is_array($tool) && isset($tool['name'])) {
                $definitions[] = $tool;
            }
        }

        return $definitions;
    }

    /**
     * Parse a tool call from AI response.
     */
    private function parseToolCall(string $content): ?array
    {
        // Try JSON decode first
        $decoded = json_decode($content, true);

        if (is_array($decoded) && isset($decoded['tool'])) {
            return $decoded;
        }

        // Try to extract JSON from text
        if (preg_match('/\{[^}]*"tool"\s*:\s*"[^"]+"[^}]*\}/s', $content, $matches)) {
            $extracted = json_decode($matches[0], true);
            if (is_array($extracted) && isset($extracted['tool'])) {
                return $extracted;
            }
        }

        return null;
    }

    /**
     * Execute a tool function.
     */
    private function executeTool(string $name, array $arguments, array $tools): mixed
    {
        foreach ($tools as $tool) {
            if (is_callable($tool)) {
                $ref = new \ReflectionFunction($tool);
                if ($ref->getName() === $name || $name === 'anonymous_tool') {
                    try {
                        return $ref->invokeArgs($arguments);
                    } catch (\Throwable $e) {
                        return ['error' => $e->getMessage()];
                    }
                }
            } elseif (is_array($tool) && ($tool['name'] ?? '') === $name && isset($tool['handler'])) {
                try {
                    return call_user_func($tool['handler'], ...$arguments);
                } catch (\Throwable $e) {
                    return ['error' => $e->getMessage()];
                }
            }
        }

        return ['error' => "Tool '{$name}' not found among registered tools."];
    }

    /**
     * Get all registered agent names.
     */
    public static function list(): array
    {
        return array_keys(self::$agents);
    }

    /**
     * Check if an agent is registered.
     */
    public static function has(string $name): bool
    {
        return isset(self::$agents[$name]);
    }
}
