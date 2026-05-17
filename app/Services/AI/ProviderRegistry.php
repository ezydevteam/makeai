<?php

namespace App\Services\AI;

use App\Models\AiKey;
use InvalidArgumentException;

/**
 * ProviderRegistry — resolves AI provider adapters by name.
 *
 * CAVEMAN say: "One registry to rule them all!" 🦴
 * Add new providers here when you add new adapters.
 */
class ProviderRegistry
{
    /**
     * @var array<string, class-string<ProviderInterface>>
     */
    private static array $providers = [
        'openai' => OpenAiProvider::class,
        'anthropic' => AnthropicProvider::class,
        'google' => GoogleProvider::class,
        'xai' => XaiProvider::class,
        'deepseek' => DeepSeekProvider::class,
        'openrouter' => OpenRouterProvider::class,
    ];

    /**
     * Resolve a provider instance by name.
     */
    public static function resolve(string $name): ProviderInterface
    {
        $name = strtolower($name);

        if (! isset(self::$providers[$name])) {
            throw new InvalidArgumentException("AI provider '{$name}' is not registered.");
        }

        $instance = app(self::$providers[$name]);

        // CAVEMAN MAGIC: Load balancing API keys! 🦴
        // Fetch an available key, sorted by last_used_at ASC (round-robin)
        $keyRecord = AiKey::forProvider($name)
            ->available()
            ->orderBy('last_used_at', 'asc')
            ->first();

        if ($keyRecord) {
            $instance->setApiKey($keyRecord->api_key);

            // Update last used
            $keyRecord->update(['last_used_at' => now(), 'usage_count' => $keyRecord->usage_count + 1]);
        }

        return $instance;
    }

    /**
     * Resolve a provider with a specific API key (e.g. user's personal key).
     */
    public static function resolveWithKey(string $name, string $apiKey): ProviderInterface
    {
        $name = strtolower($name);

        if (! isset(self::$providers[$name])) {
            throw new InvalidArgumentException("AI provider '{$name}' is not registered.");
        }

        $instance = app(self::$providers[$name]);
        $instance->setApiKey($apiKey);

        return $instance;
    }

    /**
     * Get the default provider.
     */
    public static function default(): ProviderInterface
    {
        $defaultProvider = settings('default_ai_provider', config('ai.default_provider', 'openai'));

        return self::resolve($defaultProvider);
    }

    /**
     * Get all registered provider names.
     */
    public static function all(): array
    {
        return array_keys(self::$providers);
    }

    /**
     * Register a new provider at runtime (for addons).
     */
    public static function register(string $name, string $class): void
    {
        self::$providers[$name] = $class;
    }

    /**
     * Get all configured (has API key) providers.
     */
    public static function configured(): array
    {
        $result = [];
        foreach (self::$providers as $name => $class) {
            try {
                $provider = app($class);
                if ($provider->isConfigured()) {
                    $result[$name] = $provider;
                }
            } catch (\Throwable) {
                // Skip misconfigured providers
            }
        }

        return $result;
    }
}
