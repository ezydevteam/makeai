<?php

namespace Addons\AiKnowledgeBase\Http\Controllers\Public;

use Addons\AiKnowledgeBase\Models\KbArticle;
use Addons\AiKnowledgeBase\Models\KbCategory;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KbHomeController extends Controller
{
    /** How many popular articles the landing page shows — a 3-column grid, filled. */
    private const FEATURED_LIMIT = 9;

    public function index(Request $request)
    {
        $categories = KbCategory::active()
            ->withCount(['articles' => fn ($q) => $q->published()])
            ->get();

        $activeCategory = null;
        $categoryArticles = [];

        if ($request->filled('category')) {
            $activeCategory = KbCategory::active()
                ->where('slug', $request->query('category'))
                ->first();
            
            if ($activeCategory) {
                $categoryArticles = KbArticle::published()
                    ->inCategory($activeCategory->id)
                    ->orderByDesc('published_at')
                    ->with('category')
                    ->get(['id', 'ulid', 'title', 'slug', 'excerpt', 'views', 'helpful_count', 'published_at']);
            }
        }

        // The nine most-read articles. Not paginated: this is a teaser row on the landing
        // page and the full list lives at /articles, so a paginator here only added page
        // links that scrolled the visitor through the whole library from the front door.
        $featuredArticles = KbArticle::published()
            ->orderByDesc('views')
            ->with('category')
            ->limit(self::FEATURED_LIMIT)
            ->get(['id', 'ulid', 'title', 'slug', 'excerpt', 'views', 'helpful_count', 'published_at']);

        // Drives the "View more" button — hidden when the nine above are the whole library.
        $publishedCount = KbArticle::published()->count();

        $pageTitle = $activeCategory
            ? $activeCategory->name . ' - ' . addon_setting('ai-knowledge-base', 'page_title', 'Help Center')
            : addon_setting('ai-knowledge-base', 'page_title', 'Help Center');
        $description = $activeCategory
            ? $activeCategory->description
            : addon_setting('ai-knowledge-base', 'page_description', '');

        return Inertia::render('Addons/ai-knowledge-base/Public/Home', [
            'categories' => $categories,
            'featuredArticles' => $featuredArticles,
            'hasMoreArticles' => $publishedCount > self::FEATURED_LIMIT,
            'activeCategory' => $activeCategory,
            'categoryArticles' => $categoryArticles,
            // Site-free — Home.vue passes this to <Head :title>, which the global callback
            // suffixes with the site name.
            'meta' => [
                'title' => $pageTitle,
                'description' => $description,
            ],
            // Server-rendered head payload for app.blade.php. Without it this indexable
            // page fell back to the bare site name and emitted no canonical.
            'seo' => [
                'title' => document_title($pageTitle),
                'description' => $description,
                // Query-less: ?category/?page variants consolidate to the one help URL.
                'canonical' => route('addon.kb.public.home'),
                'og' => [
                    'title' => $pageTitle,
                    'description' => $description,
                    'type' => 'website',
                    'url' => route('addon.kb.public.home'),
                ],
            ],
        ]);
    }
}
