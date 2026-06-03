<?php

namespace App\Services\AI;

use App\Models\AiKey;
use App\Services\AI\Contracts\AiDriverInterface;
use App\Services\AI\Drivers\LaravelAiDriver;
use InvalidArgumentException;

/**
 * ProviderRegistry — resolves AI provider drivers with round-robin key load balancing.
 *
 * All providers are now backed by LaravelAiDriver, which wraps the
 * official Laravel AI SDK (laravel/ai) behind AiDriverInterface.
 *
 * To add a new provider:
 *   1. Add to config/ai.php 'providers' array
 *   2. Register here in $providers map
 *   3. Add API keys to ai_keys DB table
 */
class ProviderRegistry
{
    /**
     * @var array<string, string>  provider name → driver driver name
     */
    private static array $providers = [
        'openai' => 'openai',
        'anthropic' => 'anthropic',
        'google' => 'google',
        'gemini' => 'google',
        'xai' => 'xai',
        'deepseek' => 'deepseek',
        'openrouter' => 'openrouter',
        'groq' => 'groq',
        'mistral' => 'mistral',
    ];

    /**
     * Resolve a provider driver instance by name.
     *
     * Uses round-robin API key load balancing from ai_keys table.
     */
    public static function resolve(string $name): AiDriverInterface
    {
        $driverName = self::normalizeName($name);

        $keyRecord = AiKey::forProvider($driverName)
            ->available()
            ->orderBy('last_used_at', 'asc')
            ->first();

        $apiKey = null;
        if ($keyRecord) {
            $apiKey = $keyRecord->api_key;
            $keyRecord->update(['last_used_at' => now(), 'usage_count' => $keyRecord->usage_count + 1]);
        }

        $driver = new LaravelAiDriver($driverName, $apiKey);

        return $driver;
    }

    /**
     * Resolve a provider with a specific API key (e.g. user's personal key).
     */
    public static function resolveWithKey(string $name, string $apiKey): AiDriverInterface
    {
        $driverName = self::normalizeName($name);

        return new LaravelAiDriver($driverName, $apiKey);
    }

    /**
     * Get the default provider driver.
     */
    public static function default(): AiDriverInterface
    {
        $defaultProvider = settings('default_ai_provider', config('ai.default', 'openai'));

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
    public static function register(string $name, string $driverName): void
    {
        self::$providers[strtolower($name)] = strtolower($driverName);
    }

    /**
     * Check if a provider is registered.
     */
    public static function has(string $name): bool
    {
        return isset(self::$providers[strtolower($name)]);
    }

    /**
     * Get all configured (has API key) providers.
     *
     * @return AiDriverInterface[]
     */
    public static function configured(): array
    {
        $result = [];
        foreach (self::$providers as $name => $driverName) {
            try {
                $driver = new LaravelAiDriver($driverName, settings("{$driverName}_api_key"));
                if ($driver->isConfigured() || AiKey::forProvider($driverName)->available()->exists()) {
                    $result[$name] = $driver;
                }
            } catch (\Throwable) {
                // Skip misconfigured providers
            }
        }

        return $result;
    }

    /**
     * Normalize provider name to driver name.
     */
    private static function normalizeName(string $name): string
    {
        $name = strtolower($name);

        if (config("ai.providers.{$name}") !== null) {
            return $name;
        }

        if (isset(self::$providers[$name])) {
            return self::$providers[$name];
        }

        throw new InvalidArgumentException("AI provider '{$name}' is not registered.");
    }
}
