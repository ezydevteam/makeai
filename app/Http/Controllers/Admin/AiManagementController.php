<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiKey;
use App\Models\AiModel;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;

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
    public function externalApis()
    {
        return Inertia::render('Admin/AI/ExternalApis', [
            'integrations' => $this->externalIntegrationPayload(),
        ]);
    }

    /**
     * Update non-LLM external API integrations.
     */
    public function updateExternalApis(Request $request)
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
            if (! in_array($selectedProvider, $providers, true)) {
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

        return back()->with('success', translate('External API settings updated successfully.'));
    }

    private function externalIntegrationPayload(): array
    {
        $catalog = config('external-tools.integrations', []);

        return collect($catalog)->mapWithKeys(function (array $integration, string $integrationSlug) {
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
                'provider' => settings("external_{$integrationSlug}_provider", array_key_first($integration['providers'] ?? [])),
                'timeout' => (int) settings("external_{$integrationSlug}_timeout", 30),
                'fixed_credit_cost' => settings("external_{$integrationSlug}_fixed_credit_cost", '0'),
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
