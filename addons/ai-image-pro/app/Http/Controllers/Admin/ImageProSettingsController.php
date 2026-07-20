<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Http\Controllers\Admin;

use Addons\AiImagePro\Models\AipPreset;
use Addons\AiImagePro\Services\LandingContentService;
use Addons\AiImagePro\Services\OperationRegistry;
use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\Plan;
use App\Services\AccessLevelService;
use App\Services\AI\CreditPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin settings screen — the single place every knob in `addon.json` is
 * edited, plus the per-operation configuration and the style presets.
 *
 * Two rules keep this correct: encrypted keys submitted empty are *skipped* (an
 * admin who doesn't retype a secret keeps the stored one), and the per-operation
 * grid is persisted as one JSON blob under the `operations` key — the same blob the
 * registry reads and sanitises, so an admin can never store a value that would let
 * an op escape its shipped tier/billing/inputs.
 */
class ImageProSettingsController extends Controller
{
    public function __construct(
        private readonly OperationRegistry $registry,
        private readonly AccessLevelService $accessLevels,
        private readonly LandingContentService $landing,
    ) {
    }

    public function edit(): Response
    {
        return Inertia::render('Addons/ai-image-pro/Admin/Settings', [
            'settings' => $this->currentSettings(),
            'apiKeyStatus' => $this->apiKeyStatus(),
            'operations' => $this->operationsForAdmin(),
            'accessLevels' => $this->accessLevels->getOptions(),
            'imageModels' => AiModel::query()
                ->ofType('image')
                ->orderBy('name')
                ->get(['slug', 'name', 'provider', 'is_active', 'meta'])
                ->map(fn (AiModel $model) => [
                    'slug' => $model->slug,
                    'name' => $model->name,
                    'provider' => $model->provider,
                    'is_active' => (bool) $model->is_active,
                    // What the model really costs us, and what that derives to in credits
                    // (USD × markup ÷ credit price) — the number charged when no override
                    // is set. Shown under the input so the admin edits against the truth.
                    'cost_per_unit' => (float) data_get($model->meta, 'cost_per_unit', 0),
                    'derived_credits' => $this->derivedCreditsFor($model),
                    // meta.credits_per_unit — an explicit per-image credit price that
                    // TokenGuard honours ahead of the USD-derived cost. Null = not set.
                    'credits_override' => $this->creditsOverrideOf($model),
                ])
                ->all(),
            'presets' => AipPreset::orderBy('sort')->orderBy('name')->get(),
            // The landing page's FAQ section reuses the site's own FAQ manager, so the
            // admin picks a category here rather than maintaining a second list.
            'faqCategories' => $this->landing->faqCategories(),
            // Active paid plans, so the admin can set a per-plan daily operation limit.
            // Empty unless pro is available: on a Regular license (or Extended with
            // subscriptions off) plans cannot apply to anyone, so offering per-plan
            // limits would be a control that silently does nothing. Same gate
            // AccessLevelService uses before it publishes plan access levels.
            'plans' => $this->plansForLimits(),
            // Drives the copy on the per-plan panel, so quota-mode operators are told
            // *why* the table is empty instead of being asked to create plans.
            'proAvailable' => isProAvailable(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'operations' => ['nullable', 'array'],
        ]);

        foreach ($this->settingTypes() as $key => $type) {
            if (! $request->has($key)) {
                continue;
            }

            $value = $request->input($key);

            // Never clear a stored secret just because the field came back empty —
            // the masked UI submits '' when the admin leaves it untouched.
            if ($type === 'encrypted' && ($value === null || $value === '')) {
                continue;
            }

            addon_setting_set(OperationRegistry::SLUG, $key, $this->cast($value, $type), $type);
        }

        if ($request->has('operations')) {
            addon_setting_set(
                OperationRegistry::SLUG,
                'operations',
                $this->sanitizeOperations((array) $request->input('operations', [])),
                'json',
            );
        }

        if ($request->has('model_credits')) {
            $this->saveModelCreditOverrides((array) $request->input('model_credits', []));
        }

        return redirect()->route('addon.aip.admin.settings')
            ->with('flash', translate('Settings saved.'));
    }

    /**
     * The plans an operator may set a per-plan daily limit for. None when pro is
     * unavailable — see the note at the call site.
     *
     * @return array<int, array{slug: string, name: string}>
     */
    private function plansForLimits(): array
    {
        if (! isProAvailable()) {
            return [];
        }

        return Plan::query()
            ->where('is_active', true)
            ->where('is_free', false)
            ->orderBy('sort_order')
            ->get(['slug', 'name'])
            ->map(fn (Plan $plan) => ['slug' => (string) $plan->slug, 'name' => (string) $plan->name])
            ->all();
    }

    // ── Media (per-image) pricing ─────────────────────────────────────────────

    /**
     * Persist the admin's per-image credit price for each image model.
     *
     * This writes `meta.credits_per_unit`, which is the override TokenGuard already
     * honours *ahead of* the USD-derived cost (mediaCreditCost step 1). That is why
     * this lives on the model and not on the operation: generate / variations /
     * prompt_edit can each run on any model, and each model costs a different amount,
     * so a single number on the operation row would be wrong the moment the user
     * switched model. Setting it here re-prices all three ops at once, and the same
     * function that quotes the price in the Studio is the one that charges it — they
     * cannot drift apart.
     *
     * A blank / null value REMOVES the override, restoring the derived cost.
     *
     * @param  array<string, mixed>  $credits  slug => credits-per-image (or blank)
     */
    private function saveModelCreditOverrides(array $credits): void
    {
        $models = AiModel::query()->ofType('image')->get()->keyBy('slug');

        foreach ($credits as $slug => $value) {
            $model = $models->get((string) $slug);

            if (! $model) {
                continue;
            }

            $meta = is_array($model->meta) ? $model->meta : [];

            if ($value === null || $value === '' || ! is_numeric($value) || (float) $value < 0) {
                unset($meta['credits_per_unit']);
            } else {
                $meta['credits_per_unit'] = round((float) $value, 2);
            }

            $model->meta = $meta;
            $model->save();
        }
    }

    /**
     * The credits one image costs today — resolved by the very function that charges
     * the user, so the admin screen can never quote a number the invoice contradicts.
     */
    private function derivedCreditsFor(AiModel $model): float
    {
        // Ask for the derived cost specifically, i.e. what would be charged if the
        // admin had set no override: temporarily read past meta.credits_per_unit.
        $costPerUnit = (float) data_get($model->meta, 'cost_per_unit', 0);

        if ($costPerUnit <= 0) {
            $costPerUnit = (float) config('ai.media_costs.image', 0);
        }

        if ($costPerUnit <= 0) {
            return (float) config('ai.media_credits.image', 4);
        }

        return (float) CreditPricingService::deriveCreditsPerUnit($costPerUnit);
    }

    private function creditsOverrideOf(AiModel $model): ?float
    {
        $override = data_get($model->meta, 'credits_per_unit');

        return $override === null ? null : (float) $override;
    }

    public function storePreset(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->presetRules());

        AipPreset::create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'prompt_suffix' => $validated['prompt_suffix'] ?? null,
            'negative_prompt' => $validated['negative_prompt'] ?? null,
            'thumb_path' => $this->storeThumb($request),
            'sort' => (int) ($validated['sort'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()->route('addon.aip.admin.settings')
            ->with('flash', translate('Preset created.'));
    }

    public function updatePreset(Request $request, AipPreset $preset): RedirectResponse
    {
        $validated = $request->validate($this->presetRules());

        $data = [
            'name' => $validated['name'],
            'prompt_suffix' => $validated['prompt_suffix'] ?? null,
            'negative_prompt' => $validated['negative_prompt'] ?? null,
            'sort' => (int) ($validated['sort'] ?? $preset->sort),
            'is_active' => (bool) ($validated['is_active'] ?? $preset->is_active),
        ];

        if ($request->hasFile('thumb')) {
            $this->deleteThumb($preset);
            $data['thumb_path'] = $this->storeThumb($request);
        }

        $preset->update($data);

        return redirect()->route('addon.aip.admin.settings')
            ->with('flash', translate('Preset updated.'));
    }

    public function destroyPreset(AipPreset $preset): RedirectResponse
    {
        $this->deleteThumb($preset);
        $preset->delete();

        return redirect()->route('addon.aip.admin.settings')
            ->with('flash', translate('Preset deleted.'));
    }

    // ── Settings assembly ─────────────────────────────────────────────────────

    /**
     * Every addon.json key except the encrypted secrets, read back with its stored
     * type applied.
     *
     * @return array<string, mixed>
     */
    private function currentSettings(): array
    {
        $settings = [];

        foreach ($this->settingTypes() as $key => $type) {
            if ($type === 'encrypted') {
                continue;
            }

            $settings[$key] = $this->cast(
                addon_setting(OperationRegistry::SLUG, $key, $this->defaultFor($key)),
                $type,
            );
        }

        return $settings;
    }

    /**
     * Masked status for each encrypted key: a placeholder when a secret is stored,
     * an empty string when it is not.
     *
     * @return array<string, string>
     */
    private function apiKeyStatus(): array
    {
        $status = [];

        foreach ($this->encryptedKeys() as $key) {
            $stored = addon_setting(OperationRegistry::SLUG, $key);
            $status[$key] = is_string($stored) && $stored !== '' ? str_repeat('•', 12) : '';
        }

        return $status;
    }

    /**
     * The per-operation grid as the admin edits it: shipped defaults with the
     * admin's overrides already merged in by the registry.
     *
     * @return array<int, array<string, mixed>>
     */
    private function operationsForAdmin(): array
    {
        $engineKeys = $this->registry->engineKeys();

        return collect($this->registry->all())->map(function (array $op) use ($engineKeys) {
            return [
                'key' => $op['key'],
                'label' => translate($op['label']),
                'group' => $op['group'],
                'tier' => $op['tier'],
                'billing' => $op['billing'],
                'enabled' => (bool) $op['enabled'],
                'access' => $op['access'],
                'provider' => $op['provider'],
                'providers' => $op['providers'],
                'credits' => $this->registry->credits($op['key']),
                'available' => (bool) $op['available'],
                'requires_key' => array_key_exists($op['provider'], $engineKeys),
            ];
        })->values()->all();
    }

    /**
     * Keep only the operator-owned fields for known operations. The registry
     * sanitises again on read, but there is no reason to persist junk.
     *
     * @param  array<string, mixed>  $operations
     * @return array<string, array<string, mixed>>
     */
    private function sanitizeOperations(array $operations): array
    {
        $known = $this->registry->keys();
        $clean = [];

        foreach ($operations as $key => $config) {
            if (! in_array($key, $known, true) || ! is_array($config)) {
                continue;
            }

            $entry = [];

            if (array_key_exists('enabled', $config)) {
                $entry['enabled'] = (bool) $config['enabled'];
            }

            if (array_key_exists('access', $config) && is_string($config['access'])) {
                $entry['access'] = $config['access'];
            }

            if (array_key_exists('provider', $config) && is_string($config['provider'])) {
                $entry['provider'] = $config['provider'];
            }

            if (array_key_exists('credits', $config)) {
                $entry['credits'] = max(0, (int) $config['credits']);
            }

            if ($entry !== []) {
                $clean[$key] = $entry;
            }
        }

        return $clean;
    }

    // ── Preset helpers ────────────────────────────────────────────────────────

    /**
     * @return array<string, array<int, string>>
     */
    private function presetRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'prompt_suffix' => ['nullable', 'string', 'max:2000'],
            'negative_prompt' => ['nullable', 'string', 'max:1000'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'thumb' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'preset';
        $slug = $base;
        $suffix = 1;

        while (AipPreset::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$suffix);
        }

        return $slug;
    }

    private function storeThumb(Request $request): ?string
    {
        if (! $request->hasFile('thumb')) {
            return null;
        }

        return $request->file('thumb')->store(OperationRegistry::SLUG . '/presets', 'public') ?: null;
    }

    private function deleteThumb(AipPreset $preset): void
    {
        if ($preset->thumb_path && Storage::disk('public')->exists($preset->thumb_path)) {
            Storage::disk('public')->delete($preset->thumb_path);
        }
    }

    // ── Type maps ─────────────────────────────────────────────────────────────

    /**
     * The type of every persisted setting key, matching addon.json. Drives both the
     * read-back cast and the write-time `addon_setting_set` type.
     *
     * @return array<string, string>
     */
    private function settingTypes(): array
    {
        $types = [];

        foreach ($this->manifestSettings() as $key => $setting) {
            $types[$key] = $this->storableType((string) ($setting["type"] ?? "string"));
        }

        // `operations` is persisted by its own sanitiser below, not the generic loop.
        unset($types["operations"]);

        return $types;
    }
    /**
     * @return array<int, string>
     */
    private function encryptedKeys(): array
    {
        return array_keys(array_filter($this->settingTypes(), fn (string $type) => $type === 'encrypted'));
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'json' => $this->castJson($value),
            default => $value === null ? '' : (string) $value,
        };
    }

    private function castJson(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return $decoded ?? null;
        }

        return is_array($value) ? $value : null;
    }

    /**
     * The shipped default for a key, read from the manifest — the one place it is
     * declared. Hand-maintaining a second copy here is how `studio_access` once
     * ended up defaulting to `login` in code while addon.json said `guest`.
     */
    private function defaultFor(string $key): mixed
    {
        return $this->manifestSettings()[$key]['default'] ?? null;
    }

    /**
     * The manifest's `settings` block, keyed by setting key. Cached per request —
     * this is read once per key across currentSettings()/settingTypes().
     *
     * @return array<string, array<string, mixed>>
     */
    private function manifestSettings(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $declared = addon_manifest(OperationRegistry::SLUG)['settings'] ?? [];

        $cache = [];
        foreach ($declared as $setting) {
            if (isset($setting['key'])) {
                $cache[$setting['key']] = $setting;
            }
        }

        return $cache;
    }

    /**
     * Manifest types that the settings table's enum does not accept must be stored
     * as a compatible one. `select`, `textarea`, `color` etc only ever hold a scalar
     * string, so `string` is the correct storage type — same rule AddonService uses.
     */
    private function storableType(string $manifestType): string
    {
        return in_array($manifestType, ['string', 'boolean', 'integer', 'json', 'encrypted'], true)
            ? $manifestType
            : 'string';
    }
}
