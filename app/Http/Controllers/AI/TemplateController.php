<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AiTemplate;
use App\Models\AiUsageLog;
use App\Models\Language;
use App\Services\AI\PromptBuilder;
use App\Services\AI\ToolAccessService;
use App\Services\AI\ToolCatalogCacheService;
use App\Services\AI\ToolSeoService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * TemplateController — frontend AI tool pages.
 *
 * Ref: AI_SaaS_Master_Prompt Parts P13–P15
 */
class TemplateController extends Controller
{
    public function __construct(
        private ToolSeoService $seoService,
        private ToolCatalogCacheService $toolCatalog,
        private ToolAccessService $toolAccess,
    ) {}

    /**
     * Template gallery — all active templates organized by category.
     */
    public function index(Request $request)
    {
        $templates = $this->toolCatalog->activeTools()
            ->map(function (array $tool) {
                $tool['toolCategory'] = $tool['category'];
                $tool['category'] = $tool['category_key'];

                return $tool;
            });

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

        // Also get legacy string categories for backward compat
        $legacyCategories = AiTemplate::active()
            ->whereNull('category_id')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->sort()
            ->values()
            ->toArray();

        // Featured tools
        $featured = $templates->where('is_featured', true)->take(6)->values();

        return Inertia::render('AI/Templates', [
            'templates' => $templates,
            'categories' => $categories,
            'legacyCategories' => $legacyCategories,
            'featured' => $featured,
            'initialCategory' => $initialCategory,
        ]);
    }

    /**
     * Tool page — unified template execution + content sections.
     *
     * Ref: P15.14 — ToolPage layout with Input/Output panels + content tabs
     */
    public function show(string $slug)
    {
        $template = AiTemplate::where('slug', $slug)
            ->where('is_active', true)
            ->with(['toolCategory:id,name,slug,icon,color'])
            ->withCount('favorites')
            ->firstOrFail();

        if ($this->toolAccess->requiresAuth($template) && ! auth()->check()) {
            return redirect()->route('login')->with('error', translate('You must be logged in to access this tool.'));
        }

        if ($template->isProRequired() && ! auth()->user()?->isPro()) {
            return redirect()->back()->with('error', translate('This tool requires a Pro plan. Please upgrade to continue.'));
        }

        // Get SEO meta + schemas
        $seo = $this->seoService->getMeta($template);
        $schemas = $this->seoService->getSchemas($template);

        // Get related tools
        $relatedTools = $template->show_related_tools
            ? $template->relatedTools(3)->map->only(['name', 'slug', 'description', 'icon', 'color', 'avg_rating'])
            : [];

        // Get approved reviews (first page)
        $reviews = $template->show_reviews
            ? $template->approvedReviews()
                ->with('user:id,name,avatar')
                ->orderByDesc('helpful_count')
                ->orderByDesc('created_at')
                ->paginate(10)
            : null;

        // User's review (if authenticated)
        $userReview = auth()->check()
            ? $template->reviews()->where('user_id', auth()->id())->first()
            : null;

        // Cost estimate
        $estimatedCredits = null;
        $showCreditCosts = (bool) settings('show_tool_credit_costs', true);
        if ($showCreditCosts && auth()->check()) {
            $model = $template->model_override ?? settings('default_ai_model', 'gpt-4o-mini');
            $promptBuilder = app(PromptBuilder::class);
            $estimatedCredits = $promptBuilder->estimateCost($template, $model);
        }

        $safeTemplate = $this->toolCatalog->toolBySlug($template->slug);
        $safeTemplate['toolCategory'] = $safeTemplate['category'];
        $safeTemplate['category'] = $safeTemplate['category_key'];
        $safeTemplate['favorites_count'] = $template->favorites_count;
        $safeTemplate['is_favorited'] = auth()->check()
            ? $template->favorites()->where('user_id', auth()->id())->exists()
            : false;

        return Inertia::render('AI/ToolPage', [
            'template' => $safeTemplate,
            'seo' => $seo,
            'schemas' => $schemas,
            'relatedTools' => $relatedTools,
            'reviews' => $reviews?->items() ?? [],
            'reviewsPagination' => $reviews ? [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'next_page_url' => $reviews->nextPageUrl(),
            ] : null,
            'reviewStats' => $this->reviewStats($template),
            'userReview' => $userReview,
            'estimatedCredits' => $estimatedCredits,
            'showCreditCosts' => $showCreditCosts,
            'languages' => Language::where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['code', 'name']),
            'models' => AiModel::active()->ofType('chat')->orderBy('provider')->orderBy('name')->get(['slug', 'name', 'provider']),
            'authUser' => auth()->user()?->only('id', 'name', 'credits'),
            'canReview' => auth()->check() && AiUsageLog::where('user_id', auth()->id())
                ->where('type', 'template')
                ->where('status', 'completed')
                ->where('metadata->template_slug', $template->slug)
                ->exists(),
        ]);
    }

    /**
     * Category page — list all tools in a category.
     */
    public function category(string $slug)
    {
        $category = $this->toolCatalog->activeCategories()
            ->firstWhere('slug', $slug);

        abort_if(! $category, 404);

        return Inertia::render('AI/CategoryPage', [
            'category' => $category,
            'tools' => $this->toolCatalog->activeTools($slug),
        ]);
    }

    private function reviewStats(AiTemplate $template): array
    {
        $counts = $template->approvedReviews()
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
