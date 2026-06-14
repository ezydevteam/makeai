<?php

namespace Addons\PublicKnowledgeBase\Http\Controllers\Public;

use Addons\PublicKnowledgeBase\Models\KbArticle;
use Addons\PublicKnowledgeBase\Models\KbCategory;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class KbHomeController extends Controller
{
    public function index()
    {
        $categories = KbCategory::active()
            ->withCount(['articles' => fn ($q) => $q->published()])
            ->get();

        $featuredArticles = KbArticle::published()
            ->orderByDesc('views')
            ->limit(6)
            ->with('category')
            ->get(['id', 'ulid', 'title', 'slug', 'excerpt', 'views', 'helpful_count', 'published_at']);

        return Inertia::render('Addons/public-knowledge-base/Public/Home', [
            'categories' => $categories,
            'featuredArticles' => $featuredArticles,
            'meta' => [
                'title' => addon_setting('public-knowledge-base', 'page_title', 'Help Center'),
                'description' => addon_setting('public-knowledge-base', 'page_description', ''),
            ],
        ]);
    }
}
