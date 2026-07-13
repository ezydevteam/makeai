<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Http\Controllers\User;

use Addons\AiImagePro\Models\AipPreset;
use Addons\AiImagePro\Services\ImageAccessService;
use Addons\AiImagePro\Services\LandingContentService;
use Addons\AiImagePro\Services\ModelCatalog;
use Addons\AiImagePro\Services\OperationRegistry;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public marketing page at /ai-image — the SEO front door and the top of the
 * funnel. Everything on it is admin-authored (LandingContentService); this
 * controller only assembles it.
 *
 * A signed-in user who already has Studio access does not want a sales page, so
 * they are sent straight to the app. `?preview=1` overrides that, which is how an
 * admin checks their landing copy without signing out.
 */
class LandingController extends Controller
{
    public function __construct(
        private readonly ImageAccessService $access,
        private readonly LandingContentService $landing,
        private readonly ModelCatalog $catalog,
        private readonly OperationRegistry $registry,
    ) {
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

    public function index(Request $request): Response|RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user && ! $request->boolean('preview') && $this->access->canAccessStudio($user)) {
            return redirect()->route('addon.aip.user.studio');
        }

        return Inertia::render('Addons/ai-image-pro/User/Landing', [
            'content' => $this->landing->content(),
            'examples' => $this->landing->examples(),
            'features' => $this->landing->features(),
            'usecases' => $this->landing->usecases(),
            'benefits' => $this->landing->benefits(),
            'steps' => $this->landing->steps(),
            'faqs' => $this->landing->faqs(),
            'seo' => $this->landing->seo(),

            'models' => $this->catalog->forFrontend(),
            'defaultModel' => $this->catalog->default()?->slug,
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
}
