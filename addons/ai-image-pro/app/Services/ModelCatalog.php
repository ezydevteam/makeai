<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Services;

use Addons\AiImagePro\Exceptions\ImageOperationException;
use App\Models\AiModel;
use App\Services\AI\TokenGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The image models a user may generate with.
 *
 * There is no model list in this file, and there never should be: models are rows
 * in `ai_models` (type = image) that the operator manages from the core AI Models
 * screen, and every capability — the sizes it accepts, whether it understands a
 * negative prompt or a seed, what it costs — lives in that row's `meta` JSON. The
 * addon only narrows the set (the `generation_models` setting) and picks a default.
 *
 * Pricing is deliberately NOT computed here. `TokenGuard::mediaCreditCost()` is the
 * single source of truth for what a media unit costs (model meta → USD × markup →
 * config fallback), and it is the same function that actually charges the user, so
 * quoting anything else in the UI would be a lie.
 */
class ModelCatalog
{
    /**
     * Providers served by the core `laravel/ai` AiManager. Anything else is a model
     * the addon has to call itself (Stability, Replicate, fal.ai, Ideogram).
     *
     * @var array<int, string>
     */
    private const CORE_PROVIDERS = [
        'openai',
        'azure',
        'azure_openai',
        'google',   // the app's Gemini provider name (config key); driver is 'gemini'
        'gemini',   // tolerate the driver name too, in case a model row uses it
        'bedrock',
        'openrouter',
        'xai',
    ];

    /**
     * Every active image model the operator has allowed for generation.
     *
     * @return Collection<int, AiModel>
     */
    public function available(): Collection
    {
        $models = AiModel::query()
            ->active()
            ->ofType('image')
            ->orderBy('name')
            ->get();

        $allowed = $this->allowedSlugs();

        if ($allowed === []) {
            return $models;
        }

        return $models->filter(fn (AiModel $model) => in_array($model->slug, $allowed, true))->values();
    }

    /**
     * The model a request falls back to when the user didn't pick one (or isn't
     * allowed to). The admin's `default_model` wins; otherwise the first allowed
     * model, so a fresh install with no default configured still generates.
     */
    public function default(): ?AiModel
    {
        $available = $this->available();

        $slug = (string) addon_setting(OperationRegistry::SLUG, 'default_model', '');

        if ($slug !== '') {
            $match = $available->firstWhere('slug', $slug);

            if ($match) {
                return $match;
            }
        }

        return $available->first();
    }

    /**
     * Memoised alias map. Every serialised asset translates its slug through it, so a
     * Library page would otherwise re-read the model table once per row.
     *
     * Held for the life of the container binding — one request under PHP-FPM. A queue
     * worker keeps this singleton across jobs, but nothing it runs builds aliases
     * (generation resolves the real slug, and billing keys off it), so the worst a
     * stale map could ever cost is a wrong label, never a wrong charge.
     *
     * @var array<string, string>|null
     */
    private ?array $aliasMap = null;

    /**
     * The public identifier for a model — what the frontend, the URL and the API
     * payloads use. The real slug never leaves the server.
     *
     * A slug names the model the way the provider does (`gemini-3.1-flash-image-preview`),
     * which pins us to their naming and tells every visitor exactly which upstream
     * endpoint and version we call. An alias is ours: `google-nano-banana`.
     */
    public function alias(AiModel $model): string
    {
        return $this->aliasMap()[$model->slug] ?? $this->baseAlias($model);
    }

    /**
     * The public alias for a stored slug, or null when no such model exists any more.
     *
     * Assets record the real slug they were generated with (an immutable billing fact),
     * so anything showing one to a user has to translate it back through here first.
     */
    public function aliasFor(?string $slug): ?string
    {
        $slug = $slug !== null ? trim($slug) : '';

        return $slug !== '' ? ($this->aliasMap()[$slug] ?? null) : null;
    }

    /**
     * Real slug => public alias, for every image model on record.
     *
     * Deliberately not limited to `available()`: an asset generated months ago with a
     * model the admin has since disabled still has to render its label, and resolving
     * that against a narrower set would blank it out. Availability is enforced where it
     * belongs — in resolveSlug() — not by hiding the name.
     *
     * @return array<string, string>
     */
    public function aliasMap(): array
    {
        if ($this->aliasMap !== null) {
            return $this->aliasMap;
        }

        $models = AiModel::query()->ofType('image')->get(['slug', 'name', 'provider', 'meta']);

        $counts = [];

        foreach ($models as $model) {
            $base = $this->baseAlias($model);
            $counts[$base] = ($counts[$base] ?? 0) + 1;
        }

        $map = [];

        foreach ($models as $model) {
            $base = $this->baseAlias($model);

            // Two models deriving the same alias would leave one of them unreachable,
            // so every member of a clash gets the suffix — not just whichever happened
            // to be seen second, which would make an alias depend on row order.
            $map[$model->slug] = $counts[$base] > 1
                ? $base . '-' . substr(sha1($model->slug), 0, 6)
                : $base;
        }

        return $this->aliasMap = $map;
    }

    /**
     * The alias before clash-resolution: the admin's `meta.public_alias` if they pinned
     * one, else provider + display name — both already public, so this reveals nothing
     * the model picker doesn't. "Nano Banana" on google becomes `google-nano-banana`.
     */
    private function baseAlias(AiModel $model): string
    {
        $pinned = data_get($model->meta, 'public_alias');

        if (is_string($pinned) && trim($pinned) !== '') {
            return Str::slug($pinned);
        }

        $provider = Str::slug($model->provider);
        $name = Str::slug($model->name);

        if ($provider === '') {
            return $name;
        }

        // Plenty of models are named after their maker ("Ideogram V2" on `ideogram`), and
        // prefixing those blindly stutters into ideogram-ideogram-v2.
        if ($name === $provider || str_starts_with($name, $provider . '-')) {
            return $name;
        }

        return $provider . '-' . $name;
    }

    /**
     * Resolve a user-supplied alias to a model, honouring `allow_user_model_choice`.
     *
     * This is the only entry point for anything a visitor can influence — a query
     * string, a form field, an API body. It accepts aliases and nothing else, so the
     * real slug is not merely hidden from responses but is not a valid input either.
     *
     * @throws ImageOperationException
     */
    public function resolveAlias(?string $alias): AiModel
    {
        $alias = $alias !== null ? trim($alias) : '';

        // Mirrors resolveSlug(): with no choice made, or user choice switched off, the
        // default answers — and an unknown alias is not an error worth raising, because
        // nothing the visitor sent was ever going to be honoured.
        if ($alias === '' || ! $this->allowsUserChoice()) {
            return $this->resolveSlug(null);
        }

        $slug = array_search($alias, $this->aliasMap(), true);

        if ($slug === false) {
            throw new ImageOperationException(translate('The selected image model is not available.'));
        }

        return $this->resolveSlug($slug);
    }

    /**
     * Resolve an internal slug to a model, honouring `allow_user_model_choice`.
     *
     * Server-side callers only — the slug comes from our own records (`aip_jobs.model`),
     * never from a request. Public callers want resolveAlias().
     *
     * A slug that isn't in `available()` is rejected rather than silently swapped —
     * the user would otherwise be billed at a different model's rate than the one
     * the UI quoted.
     *
     * @throws ImageOperationException
     */
    public function resolveSlug(?string $slug): AiModel
    {
        $slug = $slug !== null ? trim($slug) : '';

        if ($slug === '' || ! $this->allowsUserChoice()) {
            $default = $this->default();

            if (! $default) {
                throw new ImageOperationException(translate('No image model is available. Please contact the administrator.'));
            }

            return $default;
        }

        $model = $this->available()->firstWhere('slug', $slug);

        if (! $model) {
            throw new ImageOperationException(translate('The selected image model is not available.'));
        }

        return $model;
    }

    /**
     * Whether users may pick a model at all, or every generation uses the default.
     */
    public function allowsUserChoice(): bool
    {
        return (bool) addon_setting(OperationRegistry::SLUG, 'allow_user_model_choice', true);
    }

    /**
     * True when this provider is generated through the core AI manager rather than
     * one of the addon's own clients.
     */
    public function isCoreProvider(string $provider): bool
    {
        return in_array(strtolower(trim($provider)), self::CORE_PROVIDERS, true);
    }

    /**
     * What one image from this model costs the user — resolved by the same function
     * that charges them (`TokenGuard::afterMedia` → `mediaCreditCost`), so the quote
     * in the Studio and the deduction on the invoice can never drift apart.
     */
    public function creditsPerImage(AiModel $model): float
    {
        return TokenGuard::mediaCreditCost('image', $model->slug, 1);
    }

    /**
     * The catalogue as the Studio consumes it. Capabilities come from `meta`; a model
     * whose meta says nothing is assumed to support nothing optional, which fails
     * closed (the UI hides a field the provider would have rejected anyway).
     *
     * `id` is the public alias, and there is deliberately no `slug` key: this array is
     * serialised into the page, so anything in it is published. Whatever comes back
     * from the browser goes through resolveAlias().
     *
     * @return array<int, array<string, mixed>>
     */
    public function forFrontend(): array
    {
        $aliases = $this->aliasMap();

        return $this->available()->map(fn (AiModel $model) => [
            'id' => $aliases[$model->slug] ?? $this->baseAlias($model),
            'name' => $model->name,
            'provider' => $model->provider,
            'credits' => $this->creditsPerImage($model),
            'sizes' => $this->sizes($model),
            'supports' => $this->supports($model),
        ])->values()->all();
    }

    /**
     * Selectable output sizes for a model (`meta.sizes`, e.g. ["1024x1024","1792x1024"]).
     *
     * @return array<int, string>
     */
    public function sizes(AiModel $model): array
    {
        $sizes = data_get($model->meta, 'sizes', []);

        if (! is_array($sizes)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($size) => is_string($size) ? $size : null,
            $sizes,
        )));
    }

    /**
     * Optional capabilities declared in `meta.supports`.
     *
     * @return array{negative_prompt: bool, seed: bool, reference: bool, batch: bool}
     */
    public function supports(AiModel $model): array
    {
        $supports = data_get($model->meta, 'supports', []);
        $supports = is_array($supports) ? $supports : [];

        return [
            'negative_prompt' => (bool) ($supports['negative_prompt'] ?? false),
            'seed' => (bool) ($supports['seed'] ?? false),
            'reference' => (bool) ($supports['reference'] ?? false),
            'batch' => (bool) ($supports['batch'] ?? false),
        ];
    }

    public function supportsCapability(AiModel $model, string $capability): bool
    {
        return (bool) ($this->supports($model)[$capability] ?? false);
    }

    /**
     * The provider-side model identifier. A slug is what *we* call the model; the
     * provider may want something else (`black-forest-labs/flux-schnell`, `V_2`,
     * `sd3.5-large`) — that lives in `meta.provider_model`.
     */
    public function providerModel(AiModel $model): string
    {
        $id = data_get($model->meta, 'provider_model');

        return is_string($id) && $id !== '' ? $id : $model->slug;
    }

    /**
     * The pinned Replicate version hash for a Replicate-hosted model.
     */
    public function replicateVersion(AiModel $model): ?string
    {
        $version = data_get($model->meta, 'replicate_version');

        return is_string($version) && $version !== '' ? $version : null;
    }

    /**
     * The model slugs the admin allows. Empty = every active image model.
     *
     * @return array<int, string>
     */
    private function allowedSlugs(): array
    {
        $stored = addon_setting(OperationRegistry::SLUG, 'generation_models');

        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        if (! is_array($stored)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($slug) => is_string($slug) && $slug !== '' ? $slug : null,
            $stored,
        )));
    }
}
