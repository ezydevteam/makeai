<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AiTool;
use App\Models\AiUsageLog;
use App\Models\Category;
use App\Models\Language;
use App\Services\AI\PromptBuilder;
use App\Services\AI\ToolAccessService;
use App\Services\AI\ToolCatalogCacheService;
use App\Services\AI\ToolSeoService;
use App\Services\AI\ToolUrlHelper;
use App\Services\AI\ToolViewTrackingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AiToolController extends Controller
{
    public function __construct(
        private ToolSeoService $seoService,
        private ToolCatalogCacheService $toolCatalog,
        private ToolAccessService $toolAccess,
        private ToolViewTrackingService $viewTracker,
        private ToolUrlHelper $urlHelper,
    ) {}

    public function index(Request $request)
    {
        $tools = $this->toolCatalog->activeTools();

        $categories = $this->toolCatalog->activeCategories();

        $requestedCategory = $request->query('category');
        $initialCategory = 'all';

        if ($requestedCategory) {
            $matchedCategory = $categories->firstWhere('slug', $requestedCategory)
                ?? $categories->firstWhere('id', (int) $requestedCategory);

            $initialCategory = $matchedCategory
                ? $matchedCategory->id
                : (string) $requestedCategory;
        }

        $featured = $tools->where('is_featured', true)->take(6)->values();

        $recentlyUsed = collect();
        if (auth()->check()) {
            // Read the dedicated tool_slug column. The old lookup dug for
            // metadata['tool_slug'], which TokenGuard never writes (it stores the slug
            // under metadata['template_slug'] and in this column), so it always came
            // back empty and the section never rendered.
            $recentSlugs = AiUsageLog::where('user_id', auth()->id())
                ->where('type', 'tool')
                ->where('status', 'completed')
                ->whereNotNull('tool_slug')
                ->orderByDesc('created_at')
                ->take(50)
                ->pluck('tool_slug')
                ->unique()
                ->take(8)
                ->values();

            if ($recentSlugs->isNotEmpty()) {
                $slugMap = $tools->keyBy('slug');
                $recentlyUsed = $recentSlugs
                    ->filter(fn ($slug) => $slugMap->has($slug))
                    ->map(fn ($slug) => $slugMap[$slug])
                    ->values();
            }
        }

        $siteName = settings('site_name', config('app.name', ''));
        // Site-free, so document_title() can apply the admin's separator and the
        // client-side callback can append the site name to the same base. Baking
        // "| :app" into the translation string put the site name beyond the reach of
        // both, and left the tab disagreeing with the server <title>.
        $directoryTitle = settings('tools_directory_meta_title')
            ?: translate('AI Tools Directory');
        $directoryDesc = settings('tools_directory_meta_description')
            ?: translate('Browse every AI tool on :app — free AI generators for writing, images, code, and more.', ['app' => $siteName]);

        return Inertia::render('AI/ToolsDirectory', [
            'tools' => $tools,
            'categories' => $categories,
            'featured' => $featured,
            'recentlyUsed' => $recentlyUsed,
            'initialCategory' => $initialCategory,
            'seo' => [
                // url()->current() drops the ?category query, so every filtered view
                // canonicalizes to the single /ai-tools directory URL (no dup content).
                'title' => document_title($directoryTitle),
                'title_page' => $directoryTitle,
                'description' => $directoryDesc,
                'canonical' => url()->current(),
                'og' => [
                    'title' => $directoryTitle,
                    'description' => $directoryDesc,
                    'type' => 'website',
                    'url' => url()->current(),
                ],
            ],
        ]);
    }

    public function show(string $slug)
    {
        // Redirect voiceover-studio tool to the addon page
        if ($slug === 'voiceover-studio' && is_addon_active('ai-voiceover')) {
            return redirect()->route('addon.vo.user.studio');
        }

        // Redirect image-editor tool to the addon page
        if ($slug === 'image-editor' && is_addon_active('ai-image-editor')) {
            return redirect()->route('addon.ie.user.editor');
        }

        $toolData = $this->toolCatalog->toolBySlug($slug);

        $isAdminPreview = request()->query('preview') === '1' && auth('admin')->check();

        // Use cached data for type check and basic validation
        if (! $toolData || ($toolData['type'] === 'rag' && ! $isAdminPreview)) {
            abort(404);
        }

        // RAG tools use their own controller + page
        if ($toolData['type'] === 'rag') {
            return app(\App\Http\Controllers\RagToolController::class)->show($slug);
        }

        // Fetch fresh model for relationships and real-time active checks
        $tool = AiTool::find($toolData['id']);

        if (! $tool || (! $tool->is_active && ! $isAdminPreview) || (! $tool->category?->is_active && ! $isAdminPreview)) {
            abort(404);
        }

        // Track view (only for real visits, not admin preview)
        if (! $isAdminPreview) {
            $this->viewTracker->record($tool->slug);
        }

        $meta = $this->seoService->getMeta($tool);
        $schemas = $this->seoService->getSchemas($tool);

        // og:image must be an absolute URL. getMeta() returns either the tool's own
        // og_image or the site default, both of which may be a bare storage key or a
        // root-relative "/storage/..." path — absolutize against the configured app URL.
        $ogImage = media_url($meta['og_image'] ?? '');
        if ($ogImage !== '' && str_starts_with($ogImage, '/')) {
            $ogImage = rtrim(config('app.url'), '/').$ogImage;
        }

        // Map the service's flat meta into the `seo` prop, which carries two contracts
        // at once (same split as PageController):
        //   - nested og/twitter + schemas[] — read by app.blade.php's server-rendered
        //     head, which is what crawlers and social scrapers actually see.
        //   - flat og_*/twitter_card keys — read by ToolPage.vue's <Head>, which is the
        //     only thing that refreshes these on client-side SPA navigation. The blade
        //     head renders once per full page load and would otherwise go stale.
        // Both must be filled from the same values or the two heads disagree.
        $seo = [
            'title' => $meta['title'],
            // Site-free portion for ToolPage.vue's <Head> <title>; the global title
            // callback appends " | <site>". Dropping it here would silently fall the
            // tab back to the tool name and lose the admin's meta_title.
            'title_page' => $meta['title_page'],
            'description' => $meta['description'],
            'keywords' => $meta['keywords'] ?? null,
            'canonical' => $meta['canonical'],
            'og' => [
                'title' => $meta['og_title'] ?? $meta['title'],
                'description' => $meta['og_description'] ?? $meta['description'],
                'image' => $ogImage ?: null,
                'type' => $meta['og_type'] ?? 'website',
                'url' => $meta['canonical'],
            ],
            'twitter' => [
                'card' => $meta['twitter_card'] ?? 'summary_large_image',
                'title' => $meta['og_title'] ?? $meta['title'],
                'description' => $meta['og_description'] ?? $meta['description'],
                'image' => $ogImage ?: null,
            ],
            'schemas' => $schemas,
            // Flat keys for the client-side <Head> in ToolPage.vue (SPA navigation).
            // og_image is the absolutized $ogImage, not the raw $meta value.
            'og_title' => $meta['og_title'] ?? $meta['title'],
            'og_description' => $meta['og_description'] ?? $meta['description'],
            'og_image' => $ogImage ?: null,
            'og_type' => $meta['og_type'] ?? 'website',
            'twitter_card' => $meta['twitter_card'] ?? 'summary_large_image',
        ];

        $relatedTools = $toolData['show_related_tools'] ?? false
            ? $tool->relatedTools(3)->map->only(['name', 'slug', 'description', 'icon', 'color', 'avg_rating'])
            : [];

        $reviews = $toolData['show_reviews'] ?? false
            ? $tool->approvedReviews()
                ->with('user:id,name,avatar')
                ->orderByDesc('helpful_count')
                ->orderByDesc('created_at')
                ->paginate(10)
            : null;

        $userReview = auth()->check()
            ? $tool->reviews()->where('user_id', auth()->id())->first()
            : null;

        $estimatedCredits = null;
        $showCreditCosts = (bool) settings('show_tool_credit_costs', true);
        if ($showCreditCosts) {
            // Use cached model_override instead of fetching from fresh model
            $model = $toolData['model_override'] ?? settings('default_ai_model', config('ai.fallback_model'));
            $promptBuilder = app(PromptBuilder::class);
            $estimatedCredits = $promptBuilder->estimateCost($tool, $model, null, auth()->user());
        }

        // Use cached max_tokens_override
        $effectiveMaxTokens = $toolData['max_tokens_override'] ?? (int) settings('default_max_tokens', 2000);

        // Resolved here rather than in the page, and outside $toolData's cached portion so a
        // change to default_tool_access_level takes effect immediately. ToolPage.vue used to
        // walk tool → category itself and fall back to 'guest' for the last step, where the
        // server falls back to settings('default_tool_access_level', 'login'). An `inherit`
        // tool therefore showed a signed-out visitor an enabled Generate button and no
        // sign-in CTA, and CheckCredits then refused the request with a 401.
        $toolData['effective_access_level'] = $tool->getEffectiveAccessLevel();

        $toolData['favorites_count'] = $tool->favorites()->count();
        $toolData['is_favorited'] = auth()->check()
            ? $tool->favorites()->where('user_id', auth()->id())->exists()
            : false;

        $restoredHistory = null;
        $restoreUlid = request()->query('restore');
        if ($restoreUlid && auth()->check()) {
            $history = \App\Models\GenerationHistory::where('ulid', $restoreUlid)
                ->where('user_id', auth()->id())
                ->first();
            if ($history) {
                $fullContent = $history->document ? $history->document->content : $history->output_preview;
                $restoredHistory = [
                    'ulid' => $history->ulid,
                    'field_values' => $history->field_values ?? [],
                    'output' => $fullContent,
                    'model' => $history->model,
                    'provider' => $history->provider,
                ];
            }
        }

        return Inertia::render('AI/ToolPage', [
            'tool' => $toolData,
            // Whether the signed-in user has a brand voice saved — drives the
            // brand-voice control on tools that support it.
            'hasBrandVoice' => (bool) auth()->user()?->brand_voice,
            'seo' => $seo,
            'schemas' => $schemas,
            'relatedTools' => $relatedTools,
            'reviews' => $reviews?->items() ?? [],
            'reviewsPagination' => $reviews ? [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'next_page_url' => $reviews->nextPageUrl(),
            ] : null,
            'reviewStats' => $this->reviewStats($tool),
            'userReview' => $userReview,
            'estimatedCredits' => $estimatedCredits,
            'showCreditCosts' => $showCreditCosts,
            'languages' => Language::where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['code', 'name']),
            'models' => AiModel::active()->ofType('chat')->orderBy('provider')->orderBy('name')->get(['slug', 'name', 'provider']),
            'authUser' => auth()->user() ? array_merge(auth()->user()->only('id', 'name', 'credits'), ['is_pro' => auth()->user()->isPro()]) : null,
            'canReview' => auth()->check() && AiUsageLog::where('user_id', auth()->id())
                ->where('type', 'tool')
                ->where('status', 'completed')
                ->where('tool_slug', $tool->slug)
                ->exists(),
            'restoredHistory' => $restoredHistory,
            'effectiveMaxTokens' => $effectiveMaxTokens,
        ]);
    }

    public function category(string $slug)
    {
        $category = $this->toolCatalog->activeCategories()
            ->firstWhere('slug', $slug);

        abort_if(! $category, 404);

        // The cached category payload feeds list pages too, so it deliberately carries no
        // SEO columns. Pull just those for this one category rather than bloating the
        // shared cache; a stale cache simply falls back to the generated title.
        $categoryMeta = Category::where('slug', $slug)->first(['meta_title', 'meta_description']);

        $name = $category['name'] ?? $slug;
        $canonical = $this->urlHelper->categoryCanonical($slug);
        $pageTitle = filled($categoryMeta?->meta_title)
            ? $categoryMeta->meta_title
            : translate(':name AI Tools', ['name' => $name]);
        $description = $categoryMeta?->meta_description
            ?: ($category['description'] ?: translate('Browse every :name AI tool on :app — free AI generators you can run in your browser.', [
                'name' => $name,
                'app' => settings('site_name', 'MakeAI'),
            ]));

        return Inertia::render('AI/CategoryPage', [
            'category' => $category,
            'tools' => $this->toolCatalog->activeTools($slug),
            // This page previously shipped no seo payload: app.blade.php fell back to the
            // bare site name for <title> and emitted no canonical, on an indexable route.
            // The category's own meta_title/meta_description columns were never read.
            'seo' => [
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
            ],
        ]);
    }

    private function reviewStats(AiTool $tool): array
    {
        $counts = $tool->approvedReviews()
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $total = max(1, (int) $counts->sum());

        return [
            'distribution' => collect(range(5, 1))->mapWithKeys(function (int $rating) use ($counts, $total): array {
                $count = (int) ($counts[$rating] ?? 0);

                return [$rating => [
                    'count' => $count,
                    'percent' => round(($count / $total) * 100),
                ]];
            })->toArray(),
        ];
    }
}
