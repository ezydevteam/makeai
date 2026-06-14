<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AiTool;
use App\Models\AiUsageLog;
use App\Models\Language;
use App\Services\AI\PromptBuilder;
use App\Services\AI\ToolAccessService;
use App\Services\AI\ToolCatalogCacheService;
use App\Services\AI\ToolSeoService;
use App\Services\AI\ToolViewTrackingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TemplateController extends Controller
{
    public function __construct(
        private ToolSeoService $seoService,
        private ToolCatalogCacheService $toolCatalog,
        private ToolAccessService $toolAccess,
        private ToolViewTrackingService $viewTracker,
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

        return Inertia::render('AI/ToolsDirectory', [
            'tools' => $tools,
            'categories' => $categories,
            'featured' => $featured,
            'initialCategory' => $initialCategory,
        ]);
    }

    public function show(string $slug)
    {
        // Redirect voiceover-studio tool to the addon page
        if ($slug === 'voiceover-studio' && is_addon_active('ai-voiceover')) {
            return redirect()->route('addon.vo.user.studio');
        }

        $toolData = $this->toolCatalog->toolBySlug($slug);

        $tool = AiTool::find($toolData['id']);

        $isAdminPreview = request()->query('preview') === '1' && auth('admin')->check();

        if (! $tool || (! $tool->is_active && ! $isAdminPreview) || (! $tool->category?->is_active && ! $isAdminPreview)) {
            abort(404);
        }

        if ($this->toolAccess->requiresAuth($tool) && ! auth()->check()) {
            return redirect()->route('login')->with('error', translate('You must be logged in to access this tool.'));
        }

        if ($tool->isProRequired() && ! auth()->user()?->isPro()) {
            return redirect()->back()->with('error', translate('This tool requires a Pro plan. Please upgrade to continue.'));
        }

        // RAG tools use their own controller + page
        if ($tool->type === 'rag') {
            return app(\App\Http\Controllers\RagToolController::class)->show($slug);
        }

        // Track view (only for real visits, not admin preview)
        if (! $isAdminPreview) {
            $this->viewTracker->record($tool->slug);
        }

        $seo = $this->seoService->getMeta($tool);
        $schemas = $this->seoService->getSchemas($tool);

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
            $model = $tool->model_override ?? settings('default_ai_model', 'gpt-4o-mini');
            $promptBuilder = app(PromptBuilder::class);
            $estimatedCredits = $promptBuilder->estimateCost($tool, $model, null);
        }

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
            'authUser' => auth()->user()?->only('id', 'name', 'credits'),
            'canReview' => auth()->check() && AiUsageLog::where('user_id', auth()->id())
                ->where('type', 'template')
                ->where('status', 'completed')
                ->where('metadata->tool_slug', $tool->slug)
                ->exists(),
            'restoredHistory' => $restoredHistory,
        ]);
    }

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
