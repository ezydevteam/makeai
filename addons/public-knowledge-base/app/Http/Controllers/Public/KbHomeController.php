<?php

namespace Addons\PublicKnowledgeBase\Http\Controllers\Public;

use Addons\PublicKnowledgeBase\Models\KbArticle;
use Addons\PublicKnowledgeBase\Models\KbCategory;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KbHomeController extends Controller
{
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

        // Popular articles, paginated (Pagination component on the frontend).
        $featuredArticles = KbArticle::published()
            ->orderByDesc('views')
            ->with('category')
            ->paginate(8, ['id', 'ulid', 'title', 'slug', 'excerpt', 'views', 'helpful_count', 'published_at'])
            ->withQueryString();

        return Inertia::render('Addons/public-knowledge-base/Public/Home', [
            'categories' => $categories,
            'featuredArticles' => $featuredArticles,
            'activeCategory' => $activeCategory,
            'categoryArticles' => $categoryArticles,
            'meta' => [
                'title' => $activeCategory 
                    ? $activeCategory->name . ' - ' . addon_setting('public-knowledge-base', 'page_title', 'Help Center')
                    : addon_setting('public-knowledge-base', 'page_title', 'Help Center'),
                'description' => $activeCategory 
                    ? $activeCategory->description 
                    : addon_setting('public-knowledge-base', 'page_description', ''),
            ],
        ]);
    }
}
