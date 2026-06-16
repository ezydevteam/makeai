<?php

namespace Addons\PublicKnowledgeBase\Http\Controllers\Admin;

use Addons\PublicKnowledgeBase\Http\Requests\Admin\KbSettingsRequest;
use App\Models\AiKey;
use App\Models\AiModel;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class KbSettingsController extends Controller
{
    public function edit()
    {
        $settings = [
            'enabled' => addon_setting('public-knowledge-base', 'enabled', true),
            'public_slug' => addon_setting('public-knowledge-base', 'public_slug', 'help'),
            'page_title' => addon_setting('public-knowledge-base', 'page_title', 'Help Center'),
            'page_description' => addon_setting('public-knowledge-base', 'page_description', ''),
            'show_vote_buttons' => addon_setting('public-knowledge-base', 'show_vote_buttons', true),
            'allow_guest_search' => addon_setting('public-knowledge-base', 'allow_guest_search', true),
            'widget_enabled' => addon_setting('public-knowledge-base', 'widget_enabled', false),
            'widget_accent_color' => addon_setting('public-knowledge-base', 'widget_accent_color', '#10b981'),
            'ai_model' => addon_setting('public-knowledge-base', 'ai_model', ''),
            'embedding_model' => addon_setting('public-knowledge-base', 'embedding_model', ''),
            'top_k' => addon_setting('public-knowledge-base', 'top_k', 5),
            'max_answer_tokens' => addon_setting('public-knowledge-base', 'max_answer_tokens', 512),
            'provider' => addon_setting('public-knowledge-base', 'provider', ''),
        ];

        $providers = $this->configuredProviders();

        return Inertia::render('Addons/public-knowledge-base/Admin/Settings', [
            'settings' => $settings,
            'providers' => $providers,
        ]);
    }

    public function update(KbSettingsRequest $request)
    {
        $validated = $request->validated();
        $providers = $this->configuredProviders();

        $provider = $validated['provider'] ?? '';
        if ($provider !== '' && ! isset($providers[$provider])) {
            return back()->withErrors([
                'provider' => translate('Selected provider is not configured or has no active models.'),
            ]);
        }

        if ($provider !== '') {
            $models = $providers[$provider]['models'] ?? [];
            $selectedModel = $validated['ai_model'] ?? '';

            if ($selectedModel !== '' && ! collect($models)->contains('slug', $selectedModel)) {
                return back()->withErrors([
                    'ai_model' => translate('Selected model is not available for this provider.'),
                ]);
            }
        }

        unset($validated['_method'], $validated['_token']);

        foreach ($validated as $key => $value) {
            addon_setting_set('public-knowledge-base', $key, $value);
        }

        return back()->with('success', translate('Settings saved.'));
    }

    /**
     * Build configured AI providers with active models only.
     *
     * @return array<string, array{name: string, models: array<int, array{slug: string, name: string}>}>
     */
    private function configuredProviders(): array
    {
        $providers = config('ai.providers', []);
        $activeProviders = AiKey::available()->get()->pluck('provider')->unique()->values();

        $result = [];

        foreach ($providers as $slug => $info) {
            if (! $activeProviders->contains($slug)) {
                continue;
            }

            $models = AiModel::query()
                ->active()
                ->where('provider', $slug)
                ->orderBy('name')
                ->get(['slug', 'name'])
                ->map(fn (AiModel $model) => [
                    'slug' => $model->slug,
                    'name' => $model->name,
                ])
                ->values()
                ->all();

            if (empty($models)) {
                continue;
            }

            $result[$slug] = [
                'name' => $info['name'] ?? $slug,
                'models' => $models,
            ];
        }

        return $result;
    }
}
