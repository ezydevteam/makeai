<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Database\Seeders;

use Addons\AiImagePro\Models\AipPreset;
use Addons\AiImagePro\Services\LandingContentService;
use Addons\AiImagePro\Services\OperationRegistry;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AiKey;
use App\Models\AiModel;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Reference data for AI Image Pro. Runs on every (re)activation via
 * AddonService::installAddonSchema(), so everything here is idempotent:
 *
 *   - permissions      firstOrCreate keyed on slug (read from the manifest, never
 *                      a second hardcoded copy of the permission list)
 *   - presets          firstOrCreate keyed on slug — an admin's later edits to a
 *                      shipped preset survive re-seeding
 *   - image models     updateOrCreate keyed on slug; capability `meta` is written
 *                      only on first insert so admin tuning is never clobbered,
 *                      while `is_active` always tracks whether the provider's key
 *                      is present so nothing looks usable before it is configured
 */
class ImageProSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedPresets();
        $this->seedImageModels();
        $this->seedLandingContent();
    }

    /**
     * Persist the shipped landing-page copy so a fresh install has a complete,
     * editable page in the admin panel rather than a screen of blank fields.
     *
     * LandingContentService already falls back to these values when a key is unset,
     * so the page renders correctly either way — this exists purely so the operator
     * can SEE and edit the copy. Which is why every write is guarded by
     * isPersisted(): re-running the seeder (it runs on every activation) must never
     * overwrite what an operator has already written.
     */
    private function seedLandingContent(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $content = app(LandingContentService::class);

        // defaultText()/defaultLists() are pure constants. Deliberately NOT content() or
        // examples() — those resolve setting-first, so seeding from them would read the
        // very settings it is seeding and could re-persist a stale value as the "default".
        foreach ($content->defaultText() as $key => $value) {
            if ($value === '') {
                continue; // e.g. the About block ships empty and simply hides its section
            }

            $this->seedSettingOnce($key, $value, 'string');
        }

        foreach ($content->defaultLists() as $key => $rows) {
            $this->seedSettingOnce($key, $rows, 'json');
        }
    }

    /** Write a setting only if the operator has never set it. */
    private function seedSettingOnce(string $key, mixed $value, string $type): void
    {
        $settingKey = 'addon_' . OperationRegistry::SLUG . '_' . $key;

        if (Setting::isPersisted($settingKey)) {
            return;
        }

        addon_setting_set(OperationRegistry::SLUG, $key, $value, $type);
    }

    /**
     * Register the addon's admin permissions from the manifest and grant them to
     * the super-admin role. The slugs live in addon.json (the single source of
     * truth the admin menu and route middleware already read) — we read them back
     * rather than restating them here.
     */
    private function seedPermissions(): void
    {
        if (! Schema::hasTable('admin_permissions')) {
            return;
        }

        $manifestPath = dirname(__DIR__, 2) . '/addon.json';

        if (! is_file($manifestPath)) {
            return;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $permissions = $manifest['permissions'] ?? [];

        foreach ($permissions as $permission) {
            if (empty($permission['slug'])) {
                continue;
            }

            AdminPermission::firstOrCreate(
                ['slug' => $permission['slug']],
                [
                    'slug' => $permission['slug'],
                    'name' => $permission['name'] ?? $permission['slug'],
                    'group' => $permission['group'] ?? 'AI Image Pro',
                ],
            );
        }

        // The super-admin role holds every permission; keep it whole without
        // detaching anything an operator granted elsewhere.
        if (Schema::hasTable('admin_roles')) {
            $superAdmin = AdminRole::where('slug', 'super-admin')->first();

            if ($superAdmin) {
                $slugs = array_filter(array_column($permissions, 'slug'));
                $ids = AdminPermission::whereIn('slug', $slugs)->pluck('id');
                $superAdmin->permissions()->syncWithoutDetaching($ids);
            }
        }
    }

    /**
     * Style presets the Studio offers as one-tap prompt modifiers. Each carries a
     * prompt suffix appended to the user's prompt and a negative prompt that steers
     * the model away from the wrong medium.
     */
    private function seedPresets(): void
    {
        if (! Schema::hasTable('aip_presets')) {
            return;
        }

        $sort = 0;

        foreach ($this->presets() as $preset) {
            AipPreset::firstOrCreate(
                ['slug' => $preset['slug']],
                [
                    'name' => $preset['name'],
                    'prompt_suffix' => $preset['prompt_suffix'],
                    'negative_prompt' => $preset['negative_prompt'],
                    'sort' => ++$sort,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return array<int, array{slug: string, name: string, prompt_suffix: string, negative_prompt: string}>
     */
    private function presets(): array
    {
        return [
            [
                'slug' => 'photorealistic',
                'name' => 'Photorealistic',
                'prompt_suffix' => ', photorealistic, ultra realistic, sharp focus, natural lighting, high detail, 8k, shot on DSLR',
                'negative_prompt' => 'illustration, cartoon, painting, drawing, anime, low quality, blurry, deformed',
            ],
            [
                'slug' => 'cinematic',
                'name' => 'Cinematic',
                'prompt_suffix' => ', cinematic lighting, dramatic composition, film grain, shallow depth of field, moody atmosphere, color graded, anamorphic',
                'negative_prompt' => 'flat lighting, overexposed, low contrast, amateur, snapshot',
            ],
            [
                'slug' => 'anime',
                'name' => 'Anime',
                'prompt_suffix' => ', anime style, cel shading, vibrant colors, clean line art, detailed anime illustration, studio quality',
                'negative_prompt' => 'photorealistic, 3d render, realistic, blurry, extra limbs, bad anatomy',
            ],
            [
                'slug' => '3d-render',
                'name' => '3D Render',
                'prompt_suffix' => ', 3D render, octane render, physically based rendering, subsurface scattering, ray tracing, ultra detailed, CGI',
                'negative_prompt' => 'flat, 2d, sketch, hand drawn, low poly, noisy',
            ],
            [
                'slug' => 'watercolor',
                'name' => 'Watercolor',
                'prompt_suffix' => ', watercolor painting, soft color washes, blended pigments, textured paper, delicate brush strokes, artistic',
                'negative_prompt' => 'photograph, 3d render, sharp edges, digital, harsh lines',
            ],
            [
                'slug' => 'line-art',
                'name' => 'Line Art',
                'prompt_suffix' => ', clean line art, bold black outlines, minimal shading, vector illustration, coloring book style',
                'negative_prompt' => 'color, photorealistic, gradient, heavy shading, noise, texture',
            ],
            [
                'slug' => 'product-shot',
                'name' => 'Product Shot',
                'prompt_suffix' => ', professional product photography, studio lighting, seamless white background, soft shadows, high detail, commercial',
                'negative_prompt' => 'cluttered background, harsh shadows, low quality, blurry, distracting elements',
            ],
            [
                'slug' => 'cyberpunk-neon',
                'name' => 'Cyberpunk Neon',
                'prompt_suffix' => ', cyberpunk, neon lights, futuristic city, glowing signs, rain-soaked streets, high contrast, vibrant magenta and cyan',
                'negative_prompt' => 'daylight, natural, rural, muted colors, vintage, sepia',
            ],
            [
                'slug' => 'oil-painting',
                'name' => 'Oil Painting',
                'prompt_suffix' => ', oil painting, thick impasto brush strokes, rich textures, classical technique, canvas texture, painterly',
                'negative_prompt' => 'photograph, digital art, 3d render, smooth, flat, low detail',
            ],
            [
                'slug' => 'minimal',
                'name' => 'Minimal',
                'prompt_suffix' => ', minimalist, clean composition, generous negative space, simple shapes, muted palette, flat design, elegant',
                'negative_prompt' => 'cluttered, busy, ornate, detailed background, noisy, chaotic',
            ],
        ];
    }

    /**
     * The image models the addon can drive. Per-unit USD cost lives in
     * meta.cost_per_unit — TokenGuard::mediaCreditCost() derives the credit charge
     * from it (real cost × markup), so pricing is never a hardcoded credit number.
     * Prices are the providers' published list prices (see per-model comments).
     */
    private function seedImageModels(): void
    {
        if (! Schema::hasTable('ai_models')) {
            return;
        }

        foreach ($this->imageModels() as $def) {
            $model = AiModel::updateOrCreate(
                ['slug' => $def['slug']],
                [
                    'name' => $def['name'],
                    'provider' => $def['provider'],
                    'type' => 'image',
                    // Nothing looks usable until its provider key is configured.
                    'is_active' => $this->providerConfigured($def['provider']),
                    'cost_input_1k' => 0,
                    'cost_output_1k' => 0,
                    'credits_per_1k' => 0,
                    // Image billing is per-unit (meta.cost_per_unit), never auto
                    // per-token, so exclude these from the chat credit recalc.
                    'credits_auto' => false,
                    'max_tokens' => 0,
                ],
            );

            // Seed capabilities only on first insert — never overwrite an admin's
            // later tuning of sizes / supports / pricing on re-activation.
            if ($model->wasRecentlyCreated) {
                $model->meta = $def['meta'];
                $model->save();
            }
        }
    }

    /**
     * @return array<int, array{slug: string, name: string, provider: string, meta: array<string, mixed>}>
     */
    private function imageModels(): array
    {
        return [
            [
                // OpenAI's image model reached through vendor/laravel/ai
                // (OpenAiProvider::defaultImageModel() → 'gpt-image-2').
                'slug' => 'gpt-image-2',
                'name' => 'GPT Image 2',
                'provider' => 'openai',
                'meta' => [
                    // Source: OpenAI image API list price, ~US$0.04 per 1024×1024
                    // (medium quality) image — https://openai.com/api/pricing/
                    'cost_per_unit' => 0.04,
                    'sizes' => ['1024x1024', '1024x1536', '1536x1024'],
                    'supports' => [
                        'negative_prompt' => false, // OpenAI image API has no negative prompt
                        'seed' => false,
                        'reference' => true,        // image edits / reference via attachments
                        'batch' => true,            // n parameter
                    ],
                ],
            ],
            [
                // Google's image model, codenamed "Nano Banana". Reached through
                // vendor/laravel/ai's GeminiProvider, which implements ImageProvider —
                // so it bills through the same core media pipeline as OpenAI, with no
                // addon-side client. The slug must match the model id the provider
                // actually calls; it is editable from Admin → AI Models if Google
                // renames it.
                'slug' => 'gemini-3.1-flash-image-preview',
                'name' => 'Nano Banana',
                // 'google' is the app's provider name (config/ai.php `providers.google`,
                // driver 'gemini') — the same one every Gemini chat model uses and the key
                // the AI key vault stores. 'gemini' is only the laravel/ai driver, not a
                // provider the manager or the key lookup would resolve.
                'provider' => 'google',
                'meta' => [
                    // Source: Google's image output is billed per generated image at
                    // roughly US$0.039 for a standard 1024px render.
                    // https://ai.google.dev/gemini-api/docs/pricing
                    'cost_per_unit' => 0.039,
                    'sizes' => ['1024x1024', '1344x768', '768x1344', '1152x896', '896x1152'],
                    'supports' => [
                        'negative_prompt' => false,
                        'seed' => false,
                        'reference' => true, // conversational image editing via attachments
                        'batch' => false,    // one image per call
                    ],
                ],
            ],
            [
                'slug' => 'stable-diffusion-3',
                'name' => 'Stability SD3',
                'provider' => 'stability',
                'meta' => [
                    // Source: Stability AI — SD3 Large costs 6.5 credits/image and
                    // credits are US$10 per 1000 → US$0.065 per image.
                    // https://platform.stability.ai/pricing
                    'cost_per_unit' => 0.065,
                    'sizes' => ['1024x1024', '1152x896', '896x1152', '1216x832', '832x1216'],
                    'supports' => [
                        'negative_prompt' => true,
                        'seed' => true,
                        'reference' => true,  // image-to-image strength
                        'batch' => false,     // SD3 API returns a single image per call
                    ],
                ],
            ],
            [
                'slug' => 'flux-1.1-pro',
                'name' => 'FLUX 1.1 Pro',
                'provider' => 'replicate',
                'meta' => [
                    // Source: Replicate black-forest-labs/flux-1.1-pro — US$0.04
                    // per image (official model). https://replicate.com/black-forest-labs/flux-1.1-pro
                    'cost_per_unit' => 0.04,
                    // Pinned model version the ReplicateClient runs. Official Flux
                    // models can also be called by name; update if Replicate
                    // republishes a new version.
                    'replicate_version' => '80a09d66baa990429c2f5ae8a4306bf778a1b3775afd01cc2cc8bdbe9033769c',
                    'sizes' => ['1024x1024', '1344x768', '768x1344', '1152x896', '896x1152'],
                    'supports' => [
                        'negative_prompt' => false, // FLUX does not use a negative prompt
                        'seed' => true,
                        'reference' => false,
                        'batch' => true,            // num_outputs
                    ],
                ],
            ],
            [
                'slug' => 'ideogram-v2',
                'name' => 'Ideogram V2',
                'provider' => 'ideogram',
                'meta' => [
                    // Source: Ideogram API — V2 generation is US$0.08 per image.
                    // https://developer.ideogram.ai/api-reference/pricing
                    'cost_per_unit' => 0.08,
                    'sizes' => ['1024x1024', '1152x896', '896x1152', '1216x832', '832x1216'],
                    'supports' => [
                        'negative_prompt' => true,
                        'seed' => true,
                        'reference' => false,
                        'batch' => true, // num_images
                    ],
                ],
            ],
        ];
    }

    /**
     * A model is switched active only when its provider can actually run. Core
     * providers (openai) resolve their credentials through the shared AiKey vault;
     * the addon's own provider clients read their key from addon settings.
     */
    private function providerConfigured(string $provider): bool
    {
        // Core providers (openai, google) run through vendor/laravel/ai and resolve
        // their credentials from the shared AiKey vault, not from addon settings.
        if (in_array($provider, ['openai', 'google'], true)) {
            return Schema::hasTable('ai_keys')
                && AiKey::forProvider($provider)->available()->exists();
        }

        $keySetting = [
            'stability' => 'stability_api_key',
            'replicate' => 'replicate_api_key',
            'ideogram' => 'ideogram_api_key',
            'fal' => 'fal_api_key',
        ][$provider] ?? null;

        return $keySetting !== null
            && ! empty(addon_setting(OperationRegistry::SLUG, $keySetting));
    }
}
