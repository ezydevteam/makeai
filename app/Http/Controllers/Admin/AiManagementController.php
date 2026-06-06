<?php

namespace App\Http\Controllers\Admin;

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

        return Inertia::render('Admin/AI/Index', [
            'providerStats' => $providerStats,
            'globalSettings' => [
                'default_provider' => settings('default_ai_provider', config('ai.default_provider')),
                'max_tokens' => settings('max_tokens_per_request', config('ai.limits.max_tokens_per_request')),
                'show_tool_credit_costs' => (bool) settings('show_tool_credit_costs', true),
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
            'max_tokens' => 'required|integer|min:1|max:128000',
            'show_tool_credit_costs' => 'required|boolean',
        ]);

        Setting::setValue('default_ai_provider', $data['default_provider'], 'string', 'ai');
        Setting::setValue('max_tokens_per_request', $data['max_tokens'], 'integer', 'ai');
        Setting::setValue('default_max_tokens', $data['max_tokens'], 'integer', 'ai');
        Setting::setValue('show_tool_credit_costs', $data['show_tool_credit_costs'], 'boolean', 'ai');

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

        return Inertia::render('Admin/AI/Provider', [
            'provider' => [
                'slug' => $slug,
                'name' => $providerInfo['name'],
            ],
            'keys' => AiKey::forProvider($slug)->get(),
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
            $modelList = $providerInfo['models'] ?? [];
            $testModel = $modelList[0] ?? 'gpt-4o-mini';

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
        $defaultProvider = settings('default_ai_provider', config('ai.default_provider'));
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

        try {
            $instance = $fqcn::fromSettings();
            $result = $instance->testConnection();

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
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
        $tail = substr($secret, -4);

        return $tail ? "•••• {$tail}" : '••••';
    }
}
