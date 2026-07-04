<?php

namespace App\Http\Controllers\Admin\AI;

use App\Http\Controllers\Controller;
use App\Models\AiKey;
use App\Models\AiModel;
use App\Models\Setting;
use App\Services\AI\ProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Throwable;

class AiManagementController extends Controller
{
    /**
     * Display AI Management overview.
     */
    public function index()
    {
        $providers = config('ai.providers', []);
        $activeKeys = AiKey::available()->get()->groupBy('provider');

        $providerStats = [];
        foreach ($providers as $slug => $info) {
            $providerStats[$slug] = [
                'name' => $info['name'],
                'key_count' => $activeKeys->get($slug)?->count() ?? 0,
                'model_count' => AiModel::where('provider', $slug)->active()->count(),
            ];
        }

        // Build provider → models map for the default model select
        $providerModels = [];
        foreach ($providers as $slug => $info) {
            $models = AiModel::where('provider', $slug)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['slug', 'name'])
                ->map(fn (AiModel $m) => ['slug' => $m->slug, 'name' => $m->name])
                ->values()
                ->toArray();

            if (! empty($models)) {
                $providerModels[$slug] = $models;
            }
        }

        return Inertia::render('Admin/AI/Providers/Index', [
            'providerStats' => $providerStats,
            'providerModels' => $providerModels,
            'globalSettings' => [
                'default_provider' => settings('default_ai_provider', config('ai.default', 'openai')),
                'default_model' => settings('default_ai_model', 'gpt-4o-mini'),
                'fallback_provider' => settings('fallback_ai_provider', ''),
                'fallback_model' => settings('fallback_ai_model', ''),
                'max_tokens' => settings('max_tokens_per_request', config('ai.limits.max_tokens_per_request')),
                'show_tool_credit_costs' => (bool) settings('show_tool_credit_costs', true),
                // Spend controls (0 = disabled)
                'user_daily_credit_limit' => (float) settings('user_daily_credit_limit', 0),
                'user_monthly_credit_limit' => (float) settings('user_monthly_credit_limit', 0),
                'global_daily_ai_budget_usd' => (float) settings('global_daily_ai_budget_usd', 0),
                'credit_alert_threshold' => (int) settings('credit_alert_threshold', 100),
                'public_tool_max_output_chars' => (int) settings('public_tool_max_output_chars', 1200),
                'ai_max_input_chars' => (int) settings('ai_max_input_chars', 30000),
            ],
        ]);
    }

    /**
     * Update global AI settings.
     */
    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'default_provider' => 'required|string|in:'.implode(',', array_keys(config('ai.providers', []))),
            'default_model' => 'required|string|max:100',
            'fallback_provider' => 'nullable|string|max:50',
            'fallback_model' => 'nullable|string|max:100',
            'max_tokens' => 'required|integer|min:1|max:128000',
            'show_tool_credit_costs' => 'required|boolean',
            'user_daily_credit_limit' => 'nullable|numeric|min:0|max:100000000',
            'user_monthly_credit_limit' => 'nullable|numeric|min:0|max:100000000',
            'global_daily_ai_budget_usd' => 'nullable|numeric|min:0|max:1000000',
            'credit_alert_threshold' => 'nullable|integer|min:0|max:100000000',
            'public_tool_max_output_chars' => 'nullable|integer|min:0|max:100000',
            'ai_max_input_chars' => 'nullable|integer|min:1000|max:500000',
        ]);

        // Validate that the selected model belongs to the selected provider
        $modelRecord = AiModel::where('slug', $data['default_model'])
            ->where('provider', $data['default_provider'])
            ->where('is_active', true)
            ->first();

        if (! $modelRecord) {
            return back()->withErrors([
                'default_model' => translate('Selected model is not available for this provider.'),
            ]);
        }

        // Validate fallback model if fallback provider is set
        if (! empty($data['fallback_provider']) && ! empty($data['fallback_model'])) {
            $fbModel = AiModel::where('slug', $data['fallback_model'])
                ->where('provider', $data['fallback_provider'])
                ->where('is_active', true)
                ->first();

            if (! $fbModel) {
                return back()->withErrors([
                    'fallback_model' => translate('Selected fallback model is not available for this provider.'),
                ]);
            }
        }

        Setting::setValue('default_ai_provider', $data['default_provider'], 'string', 'ai');
        Setting::setValue('default_ai_model', $data['default_model'], 'string', 'ai');
        Setting::setValue('fallback_ai_provider', $data['fallback_provider'] ?? '', 'string', 'ai');
        Setting::setValue('fallback_ai_model', $data['fallback_model'] ?? '', 'string', 'ai');
        Setting::setValue('max_tokens_per_request', $data['max_tokens'], 'integer', 'ai');
        Setting::setValue('default_max_tokens', $data['max_tokens'], 'integer', 'ai');
        Setting::setValue('show_tool_credit_costs', $data['show_tool_credit_costs'], 'boolean', 'ai');

        // Spend controls (0 = disabled)
        Setting::setValue('user_daily_credit_limit', (string) ($data['user_daily_credit_limit'] ?? 0), 'string', 'ai');
        Setting::setValue('user_monthly_credit_limit', (string) ($data['user_monthly_credit_limit'] ?? 0), 'string', 'ai');
        Setting::setValue('global_daily_ai_budget_usd', (string) ($data['global_daily_ai_budget_usd'] ?? 0), 'string', 'ai');
        Setting::setValue('credit_alert_threshold', (int) ($data['credit_alert_threshold'] ?? 100), 'integer', 'ai');
        Setting::setValue('public_tool_max_output_chars', (int) ($data['public_tool_max_output_chars'] ?? 1200), 'integer', 'ai');
        Setting::setValue('ai_max_input_chars', (int) ($data['ai_max_input_chars'] ?? 30000), 'integer', 'ai');

        return back()->with('success', translate('AI settings updated successfully.'));
    }

    /**
     * Manage keys and models for a specific provider.
     */
    public function provider(string $slug)
    {
        $providerInfo = config("ai.providers.{$slug}");
        if (! $providerInfo) {
            abort(404);
        }

        return Inertia::render('Admin/AI/Providers/Provider', [
            'provider' => [
                'slug' => $slug,
                'name' => $providerInfo['name'],
            ],
            'keys' => AiKey::forProvider($slug)->get()->map(fn($k) => [
                'id' => $k->id,
                'label' => $k->label,
                'masked_key' => $this->maskSecret($k->api_key),
                'is_active' => $k->is_active,
                'usage_count' => $k->usage_count,
                'last_used_at' => $k->last_used_at,
            ]),
            'models' => AiModel::where('provider', $slug)->get(),
        ]);
    }

    /**
     * Store a new API key.
     */
    public function storeKey(Request $request, string $provider)
    {
        $request->validate([
            'api_key' => 'required|string',
            'label' => 'nullable|string|max:100',
        ]);

        AiKey::create([
            'provider' => $provider,
            'api_key' => $request->api_key,
            'label' => $request->label,
            'is_active' => true,
        ]);

        return back()->with('success', translate('API Key added successfully.'));
    }

    /**
     * Delete an API key.
     */
    public function deleteKey(AiKey $key)
    {
        $key->delete();

        return back()->with('success', translate('API Key deleted.'));
    }

    /**
     * Test connection to an AI provider using the configured API key.
     */
    public function testConnection(Request $request, string $slug): JsonResponse
    {
        $providerInfo = config("ai.providers.{$slug}");
        if (! $providerInfo) {
            return response()->json(['success' => false, 'error' => translate('Unknown provider.')], 404);
        }

        $keyRecord = null;
        $apiKey = null;

        if ($request->has('api_key') && $request->api_key !== '') {
            $apiKey = $request->api_key;
        } else {
            $keyRecord = AiKey::forProvider($slug)->available()->first();
            if ($keyRecord) {
                $apiKey = $keyRecord->api_key;
            }
        }

        if (! $apiKey) {
            return response()->json([
                'success' => false,
                'error' => translate('No API key configured. Add a key and try again.'),
            ], 422);
        }

        try {
            $driver = ProviderRegistry::resolveWithKey($slug, $apiKey);
            
            // Try database models first, then config models
            $dbModel = AiModel::where('provider', $slug)
                ->where('is_active', true)
                ->orderBy('name')
                ->first();
            
            $configModels = $providerInfo['models'] ?? [];
            $testModel = $dbModel?->slug ?? $configModels[0] ?? null;
            
            if (!$testModel) {
                return response()->json([
                    'success' => false,
                    'error' => translate('No models available for this provider. Add models first.'),
                ], 422);
            }

            $result = $driver->chatCompletion([
                ['role' => 'user', 'content' => 'Respond with exactly: OK'],
            ], $testModel, ['timeout' => 15]);

            $success = ! empty($result['content']);

            return response()->json([
                'success' => $success,
                'model' => $testModel,
                'message' => translate('Connection successful.'),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 200);
        }
    }

    /**
     * Update model settings.
     */
    public function updateModel(Request $request, AiModel $model)
    {
        $data = $request->validate([
            'is_active' => 'required|boolean',
            'cost_input_1k' => 'required|numeric|min:0',
            'cost_output_1k' => 'required|numeric|min:0',
            'credits_per_1k' => 'required|integer|min:0',
            'max_tokens' => 'required|integer|min:1',
        ]);

        $model->update($data);

        return back()->with('success', translate(':model updated.', ['model' => $model->name]));
    }

    /**
     * Manage non-LLM external API integrations for special tools.
     */
    public function integrations()
    {
        $defaultProvider = settings('default_ai_provider', config('ai.default', 'openai'));
        $aiProviders = config('ai.providers', []);
        $activeKeys = AiKey::available()->get()->groupBy('provider');

        $configuredModels = [];
        foreach ($aiProviders as $slug => $info) {
            if ($activeKeys->has($slug)) {
                $configuredModels[$slug] = [
                    'name' => $info['name'],
                    'key_count' => $activeKeys->get($slug)->count(),
                ];
            }
        }

        return Inertia::render('Admin/AI/Integrations', [
            'integrations' => $this->integrationPayload(),
            'defaultAiProvider' => $defaultProvider,
            'configuredAiProviders' => $configuredModels,
        ]);
    }

    /**
     * Test connection to an external API integration.
     */
    public function testIntegrationConnection(Request $request, string $integration): JsonResponse
    {
        $catalog = config('external-tools.integrations', []);
        $integrationConfig = $catalog[$integration] ?? null;

        if (! $integrationConfig) {
            return response()->json(['success' => false, 'error' => translate('Unknown integration.')], 404);
        }

        $serviceClass = $integrationConfig['service'] ?? null;
        if (! $serviceClass) {
            return response()->json(['success' => false, 'error' => translate('No service class defined for this integration.')], 422);
        }

        $fqcn = "\\App\\Services\\{$serviceClass}";
        if (! class_exists($fqcn) || ! method_exists($fqcn, 'testConnection')) {
            return response()->json(['success' => false, 'error' => translate('Service class does not support connection testing.')], 422);
        }

        $provider = $request->input('provider');
        $credentials = $request->input('credentials', []);
        $cacheOverrides = [];

        try {
            if ($provider && ! empty($credentials)) {
                $providerConfig = $integrationConfig['providers'][$provider] ?? null;

                if ($providerConfig) {
                    $providerCacheKey = "settings:external_{$integration}_provider";
                    $cacheOverrides[$providerCacheKey] = \Illuminate\Support\Facades\Cache::get($providerCacheKey);
                    \Illuminate\Support\Facades\Cache::forever($providerCacheKey, $provider);

                    foreach ($credentials as $key => $value) {
                        if (in_array($key, $providerConfig['secrets'] ?? [], true) || in_array($key, $providerConfig['options'] ?? [], true)) {
                            $cacheKey = "settings:external_{$integration}_{$provider}_{$key}";
                            $cacheOverrides[$cacheKey] = \Illuminate\Support\Facades\Cache::get($cacheKey);
                            \Illuminate\Support\Facades\Cache::forever($cacheKey, $value);
                        }
                    }
                }
            }

            $instance = $fqcn::fromSettings();
            $result = $instance->testConnection();

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        } finally {
            foreach ($cacheOverrides as $cacheKey => $originalValue) {
                if ($originalValue === null) {
                    \Illuminate\Support\Facades\Cache::forget($cacheKey);
                } else {
                    \Illuminate\Support\Facades\Cache::forever($cacheKey, $originalValue);
                }
            }
        }
    }

    /**
     * Update non-LLM external API integrations.
     */
    public function updateIntegrations(Request $request)
    {
        $catalog = config('external-tools.integrations', []);
        $incoming = $request->input('integrations', []);

        if (! is_array($incoming)) {
            return back()->withErrors([
                'integrations' => translate('Invalid integration payload.'),
            ]);
        }

        foreach ($catalog as $integrationSlug => $integration) {
            $payload = Arr::get($incoming, $integrationSlug, []);
            if (! is_array($payload)) {
                continue;
            }

            $providers = array_keys($integration['providers'] ?? []);
            $selectedProvider = Arr::get($payload, 'provider', $providers[0] ?? null);
            if ($selectedProvider === '__default_ai__') {
                if ($integration['ai_fallback'] ?? true) {
                    $selectedProvider = '__default_ai__';
                } else {
                    $selectedProvider = $providers[0] ?? null;
                }
            } elseif (! in_array($selectedProvider, $providers, true)) {
                $selectedProvider = $providers[0] ?? null;
            }

            settings_set("external_{$integrationSlug}_enabled", (bool) Arr::get($payload, 'enabled', false), 'boolean', 'external_apis');
            settings_set("external_{$integrationSlug}_provider", $selectedProvider, 'string', 'external_apis');

            $timeout = max(5, min(180, (int) Arr::get($payload, 'timeout', 30)));
            $fixedCreditCost = max(0, round((float) Arr::get($payload, 'fixed_credit_cost', 0), 2));

            settings_set("external_{$integrationSlug}_timeout", $timeout, 'integer', 'external_apis');
            settings_set("external_{$integrationSlug}_fixed_credit_cost", (string) $fixedCreditCost, 'string', 'external_apis');

            foreach ($integration['providers'] ?? [] as $providerSlug => $provider) {
                $providerPayload = Arr::get($payload, "providers.{$providerSlug}", []);
                if (! is_array($providerPayload)) {
                    continue;
                }

                foreach ($provider['options'] ?? [] as $optionKey) {
                    $value = Arr::get($providerPayload, "options.{$optionKey}");
                    settings_set(
                        "external_{$integrationSlug}_{$providerSlug}_{$optionKey}",
                        is_scalar($value) ? mb_substr((string) $value, 0, 1000) : '',
                        'string',
                        'external_apis'
                    );
                }

                foreach ($provider['secrets'] ?? [] as $secretKey) {
                    $value = Arr::get($providerPayload, "secrets.{$secretKey}");
                    if (is_string($value) && $value !== '') {
                        settings_set(
                            "external_{$integrationSlug}_{$providerSlug}_{$secretKey}",
                            mb_substr($value, 0, 2000),
                            'encrypted',
                            'external_apis'
                        );
                    }
                }
            }
        }

        return back()->with('success', translate('Integration settings updated successfully.'));
    }

    private function integrationPayload(): array
    {
        $catalog = config('external-tools.integrations', []);

        return collect($catalog)->mapWithKeys(function (array $integration, string $integrationSlug) {
            $providerKeys = array_keys($integration['providers'] ?? []);
            $firstProvider = $providerKeys[0] ?? null;
            $storedProvider = settings("external_{$integrationSlug}_provider", $firstProvider);

            if ($storedProvider === '__default_ai__') {
                if (!($integration['ai_fallback'] ?? true)) {
                    $storedProvider = $firstProvider;
                }
                // Keep the sentinel — frontend handles display
            } elseif (!in_array($storedProvider, $providerKeys, true) && $firstProvider) {
                $storedProvider = $firstProvider;
            }
            $providers = collect($integration['providers'] ?? [])->mapWithKeys(function (array $provider, string $providerSlug) use ($integrationSlug) {
                $secrets = collect($provider['secrets'] ?? [])->mapWithKeys(function (string $secretKey) use ($integrationSlug, $providerSlug) {
                    $stored = settings("external_{$integrationSlug}_{$providerSlug}_{$secretKey}");

                    return [$secretKey => [
                        'configured' => filled($stored),
                        'masked' => filled($stored) ? $this->maskSecret((string) $stored) : null,
                    ]];
                })->toArray();

                $options = collect($provider['options'] ?? [])->mapWithKeys(function (string $optionKey) use ($integrationSlug, $providerSlug) {
                    return [$optionKey => settings("external_{$integrationSlug}_{$providerSlug}_{$optionKey}", '')];
                })->toArray();

                return [$providerSlug => [
                    'name' => $provider['name'],
                    'secrets' => $secrets,
                    'options' => $options,
                ]];
            })->toArray();

            return [$integrationSlug => [
                'name' => $integration['name'],
                'service' => $integration['service'],
                'enabled' => (bool) settings("external_{$integrationSlug}_enabled", false),
                'provider' => $storedProvider,
                'timeout' => (int) settings("external_{$integrationSlug}_timeout", 30),
                'fixed_credit_cost' => settings("external_{$integrationSlug}_fixed_credit_cost", '0'),
                'tab' => $integration['tab'] ?? 'utilities',
                'doc_url' => $integration['doc_url'] ?? null,
                'ai_fallback' => $integration['ai_fallback'] ?? true,
                'providers' => $providers,
            ]];
        })->toArray();
    }

    private function maskSecret(string $secret): string
    {
        $length = mb_strlen($secret);

        if ($length <= 4) {
            return '••••';
        }

        if ($length <= 8) {
            $prefix = mb_substr($secret, 0, 1);
            $suffix = mb_substr($secret, -1);

            return "{$prefix}•••{$suffix}";
        }

        $prefix = mb_substr($secret, 0, 3);
        $suffix = mb_substr($secret, -4);

        return "{$prefix}•••{$suffix}";
    }

    /**
     * Show RAG settings page.
     */
    public function ragSettings()
    {
        $embeddingModels = AiModel::active()
            ->where('type', 'embedding')
            ->get(['slug', 'name', 'provider'])
            ->map(fn (AiModel $m) => [
                'value' => $m->slug,
                'label' => "{$m->name} ({$m->provider})",
                'provider' => $m->provider,
            ])
            ->values()
            ->toArray();

        $defaultEmbeddingProvider = settings('ai_embedding_provider')
            ?: settings('default_ai_provider')
            ?: config('ai.default_for_embeddings', 'openai');

        // Resolve the fallback model: explicit rag_embedding_model → first active embedding model for the resolved provider → provider-aware fallback
        $defaultEmbeddingModel = settings('rag_embedding_model', '');

        if (! $defaultEmbeddingModel) {
            $fallbackDbModel = AiModel::active()
                ->where('type', 'embedding')
                ->where('provider', $defaultEmbeddingProvider)
                ->first(['slug', 'name', 'provider']);

            if ($fallbackDbModel) {
                $defaultEmbeddingModel = $fallbackDbModel->slug;
            } else {
                $defaultEmbeddingModel = match ($defaultEmbeddingProvider) {
                    'google' => 'text-embedding-004',
                    'cohere' => 'embed-v4.0',
                    'jina' => 'jina-embeddings-v3',
                    'voyageai' => 'voyage-3',
                    default => 'text-embedding-3-small',
                };
            }
        }

        // Resolve a human name for the fallback model
        $fallbackModel = AiModel::where('slug', $defaultEmbeddingModel)
            ->where('provider', $defaultEmbeddingProvider)
            ->first();

        $fallbackLabel = $fallbackModel
            ? "{$fallbackModel->name} ({$fallbackModel->provider})"
            : "{$defaultEmbeddingModel} ({$defaultEmbeddingProvider})";

        return Inertia::render('Admin/AI/Rag', [
            'settings' => [
                'max_file_mb' => (int) settings('rag_max_file_mb', 25),
                'max_pages' => (int) settings('rag_max_pages', 300),
                'top_k' => (int) settings('rag_top_k', 6),
                'chunk_size' => (int) settings('rag_chunk_size', 800),
                'chunk_overlap' => (int) settings('rag_chunk_overlap', 100),
                'embedding_model' => settings('rag_embedding_model', ''),
                'system_prompt' => settings('rag_system_prompt', ''),
                'ephemeral_retention_days' => (int) settings('rag_ephemeral_retention_days', 7),
                'chunks_per_credit' => (int) settings('rag_chunks_per_credit', 50),
                'youtube_whisper_fallback' => (bool) settings('rag_youtube_whisper_fallback', false),
                'search_mode' => settings('rag_search_mode', 'vector'),
                'chunking_mode' => settings('rag_chunking_mode', 'fixed'),
                'map_reduce_batch_size' => (int) settings('rag_map_reduce_batch_size', 10),
                'ingest_credits_per_mb' => (float) settings('rag_ingest_credits_per_mb', 0),
                'ingest_credits_url' => (float) settings('rag_ingest_credits_url', 0),
            ],
            'embeddingModels' => $embeddingModels,
            'fallbackEmbedding' => [
                'provider' => $defaultEmbeddingProvider,
                'model' => $defaultEmbeddingModel,
                'label' => $fallbackLabel,
            ],
        ]);
    }

    /**
     * Update RAG settings.
     */
    public function updateRagSettings(Request $request)
    {
        $data = $request->validate([
            'max_file_mb' => 'required|integer|min:1|max:500',
            'max_pages' => 'required|integer|min:1|max:5000',
            'top_k' => 'required|integer|min:1|max:50',
            'chunk_size' => 'required|integer|min:100|max:8000',
            'chunk_overlap' => 'required|integer|min:0|max:2000|lt:chunk_size',
            'embedding_model' => 'nullable|string|max:100|exists:ai_models,slug',
            'system_prompt' => 'required|string|max:4000',
            'ephemeral_retention_days' => 'required|integer|min:1|max:90',
            'chunks_per_credit' => 'required|integer|min:1|max:1000',
            'youtube_whisper_fallback' => 'required|boolean',
            'search_mode' => 'required|string|in:vector,hybrid',
            'chunking_mode' => 'required|string|in:fixed,semantic',
            'map_reduce_batch_size' => 'required|integer|min:1|max:50',
            'ingest_credits_per_mb' => 'nullable|numeric|min:0|max:100000',
            'ingest_credits_url' => 'nullable|numeric|min:0|max:100000',
        ]);

        // Validate system_prompt contains {context} placeholder
        if (!str_contains($data['system_prompt'], '{context}')) {
            return back()->withErrors([
                'system_prompt' => translate('System prompt must contain the {context} placeholder.'),
            ]);
        }

        Setting::setValue('rag_max_file_mb', $data['max_file_mb'], 'integer', 'rag');
        Setting::setValue('rag_max_pages', $data['max_pages'], 'integer', 'rag');
        Setting::setValue('rag_top_k', $data['top_k'], 'integer', 'rag');
        Setting::setValue('rag_chunk_size', $data['chunk_size'], 'integer', 'rag');
        Setting::setValue('rag_chunk_overlap', $data['chunk_overlap'], 'integer', 'rag');

        // Empty embedding_model = use default provider/model from global AI settings
        Setting::setValue('rag_embedding_model', $data['embedding_model'] ?? '', 'string', 'rag');
        Setting::setValue('rag_system_prompt', $data['system_prompt'], 'string', 'rag');
        Setting::setValue('rag_ephemeral_retention_days', $data['ephemeral_retention_days'], 'integer', 'rag');
        Setting::setValue('rag_chunks_per_credit', $data['chunks_per_credit'], 'integer', 'rag');
        Setting::setValue('rag_youtube_whisper_fallback', $data['youtube_whisper_fallback'], 'boolean', 'rag');
        Setting::setValue('rag_search_mode', $data['search_mode'], 'string', 'rag');
        Setting::setValue('rag_chunking_mode', $data['chunking_mode'], 'string', 'rag');
        Setting::setValue('rag_map_reduce_batch_size', $data['map_reduce_batch_size'], 'integer', 'rag');
        Setting::setValue('rag_ingest_credits_per_mb', (string) ($data['ingest_credits_per_mb'] ?? 0), 'string', 'rag');
        Setting::setValue('rag_ingest_credits_url', (string) ($data['ingest_credits_url'] ?? 0), 'string', 'rag');

        return back()->with('success', translate('RAG settings updated successfully.'));
    }
}
