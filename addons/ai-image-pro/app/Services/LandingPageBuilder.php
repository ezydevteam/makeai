<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Services;

use Addons\AiImagePro\Models\AipPreset;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Assembles the AI Image Pro landing page — the SEO front door and the top of the
 * funnel. Everything on it is admin-authored (LandingContentService); this only
 * gathers it into the Inertia response.
 *
 * It lives in a service rather than a controller action because the page has two
 * callers with different entry rules: the `/ai-image` route (LandingController,
 * which bounces a signed-in user with Studio access straight to the app) and the
 * homepage provider, which serves the page at `/` to everyone. Neither owns the
 * page, so neither should own the assembly.
 */
class LandingPageBuilder
{
    public function __construct(
        private readonly LandingContentService $landing,
        private readonly ModelCatalog $catalog,
        private readonly OperationRegistry $registry,
    ) {
    }

    /**
     * The landing page for a visitor — null for a guest. No access checks and no
     * redirects: the caller decides who gets here.
     */
    public function render(?User $user): Response
    {
        return Inertia::render('Addons/ai-image-pro/User/Landing', [
            'content' => $this->landing->content(),
            'examples' => $this->landing->examples(),
            'features' => $this->landing->features(),
            'usecases' => $this->landing->usecases(),
            'benefits' => $this->landing->benefits(),
            'steps' => $this->landing->steps(),
            'faqs' => $this->landing->faqs(),
            // seo() returns the site-free base; expand it into the full head contract.
            // Previously the raw base went straight through, so app.blade.php rendered a
            // <title> with no site name while the client callback appended one — the tag
            // and the tab disagreed — and this indexable marketing page had no canonical.
            'seo' => $this->landingSeo(),

            'models' => $this->catalog->forFrontend(),
            'defaultModel' => ($default = $this->catalog->default()) ? $this->catalog->alias($default) : null,
            'allowModelChoice' => $this->catalog->allowsUserChoice(),
            'aspectRatios' => $this->aspectRatios(),

            // The landing prompt panel exposes the same controls as the Studio's — the
            // tool list and the style presets — so a visitor sees the full capability
            // before they commit. Both come from the registry / presets table, never a
            // hardcoded list, so an operation the admin disabled never shows up here.
            'operations' => $this->registry->forFrontend($user),
            'presets' => $this->presetsForFrontend(),

            'studioUrl' => route('addon.aip.user.studio'),
            'isGuest' => $user === null,
            'toolName' => (string) (addon_manifest(OperationRegistry::SLUG)['name'] ?? 'AI Image Generator'),
        ]);
    }

    /**
     * Style presets for the prompt panel's style chip — the same list the Studio shows.
     *
     * @return array<int, array<string, mixed>>
     */
    private function presetsForFrontend(): array
    {
        return AipPreset::active()->get()->map(fn (AipPreset $preset) => [
            'slug' => $preset->slug,
            'name' => $preset->name,
            'prompt_suffix' => $preset->prompt_suffix,
            'thumb_url' => $preset->thumb_url,
        ])->all();
    }

    /**
     * Selectable aspect ratios. Admin-set, with built-in defaults behind them —
     * kept identical to the Studio's list so the landing prompt panel and the app
     * offer the same choices.
     *
     * @return array<int, array<string, mixed>>
     */
    private function aspectRatios(): array
    {
        $stored = addon_setting(OperationRegistry::SLUG, 'aspect_ratios');

        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        if (is_array($stored) && $stored !== []) {
            return array_values($stored);
        }

        return [
            ['key' => '1:1',  'label' => 'Square',    'width' => 1024, 'height' => 1024],
            ['key' => '16:9', 'label' => 'Landscape', 'width' => 1344, 'height' => 768],
            ['key' => '9:16', 'label' => 'Portrait',  'width' => 768,  'height' => 1344],
            ['key' => '4:3',  'label' => 'Standard',  'width' => 1152, 'height' => 896],
            ['key' => '3:2',  'label' => 'Photo',     'width' => 1216, 'height' => 832],
        ];
    }

    /**
     * Full head contract for the public landing page.
     *
     * title_page stays site-free — Landing.vue puts it in <Head> and the global callback
     * appends the site name with the admin's separator. `title` is the complete tag for
     * app.blade.php, composed by document_title() so both halves match exactly.
     *
     * The canonical always points at `/ai-image`, even when the page is also served at
     * `/` as the homepage — one page reachable at two URLs needs a single indexable one.
     *
     * @return array<string, mixed>
     */
    private function landingSeo(): array
    {
        $base = $this->landing->seo();
        $pageTitle = $base['title'] ?? '';
        $description = $base['description'] ?? '';
        $canonical = route('addon.aip.user.landing');

        return [
            'title' => document_title($pageTitle),
            'title_page' => $pageTitle,
            'description' => $description,
            'canonical' => $canonical,
            'og' => [
                'title' => $pageTitle,
                'description' => $description,
                'type' => 'website',
                'url' => $canonical,
            ],
        ];
    }
}
