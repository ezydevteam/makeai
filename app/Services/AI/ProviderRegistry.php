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
 * Key selection: keys ordered by usage_count ascending (least-used first).
 * This provides true round-robin distribution under normal load.
 */
class ProviderRegistry
{
    private const MAX_ERRORS = 3;

    private const COOLDOWN_MINUTES = 5;

    /**
     * @var array<string, string> provider name → driver driver name
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
        'ollama' => 'ollama',
        'bedrock' => 'bedrock',
        'cohere' => 'cohere',
        'eleven' => 'eleven',
        'jina' => 'jina',
        'voyageai' => 'voyageai',
        'perplexity' => 'perplexity',
        'together' => 'together',
        'replicate' => 'replicate',
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
            ->orderBy('usage_count', 'asc')
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
     * Resolve a provider driver with automatic failover.
     *
     * Tries each available key for the provider, marking failed keys
     * and rotating to the next key on errors (429, 5xx, timeouts).
     * Returns null if all keys are exhausted.
     */
    public static function resolveWithFailover(string $name, ?int &$usedKeyId = null): ?AiDriverInterface
    {
        $driverName = self::normalizeName($name);

        $keys = AiKey::forProvider($driverName)
            ->available()
            ->orderBy('last_used_at', 'asc')
            ->get();

        if ($keys->isEmpty()) {
            return null;
        }

        $keyRecord = $keys->shift();
        $apiKey = $keyRecord->api_key;
        $usedKeyId = $keyRecord->id;
        $keyRecord->update(['last_used_at' => now(), 'usage_count' => $keyRecord->usage_count + 1]);

        return new LaravelAiDriver($driverName, $apiKey);
    }

    /**
     * Mark an API key as failed and rotate to the next available key.
     *
     * Increments error_count. If exceeded MAX_ERRORS, disables key for COOLDOWN_MINUTES.
     * Returns the next available driver, or null if all keys exhausted.
     */
    public static function markFailedAndRotate(string $name, int $failedKeyId, ?int &$rotationKeyId = null): ?AiDriverInterface
    {
        $driverName = self::normalizeName($name);

        $failedKey = AiKey::find($failedKeyId);
        if ($failedKey) {
            $newErrorCount = $failedKey->error_count + 1;
            $updates = ['error_count' => $newErrorCount];

            if ($newErrorCount >= self::MAX_ERRORS) {
                $updates['disabled_until'] = now()->addMinutes(self::COOLDOWN_MINUTES);
            }

            $failedKey->update($updates);
        }

        $nextKey = AiKey::forProvider($driverName)
            ->available()
            ->where('id', '!=', $failedKeyId)
            ->orderBy('last_used_at', 'asc')
            ->first();

        if (! $nextKey) {
            return null;
        }

        $apiKey = $nextKey->api_key;
        $rotationKeyId = $nextKey->id;
        $nextKey->update(['last_used_at' => now(), 'usage_count' => $nextKey->usage_count + 1]);

        return new LaravelAiDriver($driverName, $apiKey);
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
                $driver = new LaravelAiDriver($driverName);
                if (AiKey::forProvider($driverName)->available()->exists()) {
                    $result[$name] = $driver;
                }
            } catch (\Throwable) {
                // Skip misconfigured providers
            }
        }

        return $result;
    }

    /**
     * Get all enabled providers (those with API keys configured).
     * Returns provider name => array of model strings.
     */
    public static function getEnabledProviders(): array
    {
        $result = [];
        foreach (self::$providers as $name => $driverName) {
            if (! AiKey::forProvider($driverName)->available()->exists()) {
                continue;
            }
            $models = config("ai.providers.{$name}.models", []);
            if (empty($models)) continue;
            $result[$name] = $models;
        }
        return $result;
    }

    /**
     * Get flat list of available models from configured providers.
     */
    public static function availableModels(): array
    {
        $models = [];
        foreach (self::getEnabledProviders() as $providerModels) {
            foreach ($providerModels as $model) {
                $models[] = $model;
            }
        }
        return array_unique($models);
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
