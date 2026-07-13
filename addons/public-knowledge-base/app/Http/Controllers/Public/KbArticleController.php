<?php

namespace Addons\PublicKnowledgeBase\Http\Controllers\Public;

use Addons\PublicKnowledgeBase\Models\KbArticle;
use Addons\PublicKnowledgeBase\Models\KbArticleVote;
use Addons\PublicKnowledgeBase\Models\KbCategory;
use Addons\PublicKnowledgeBase\Services\KbSearchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KbArticleController extends Controller
{
    /**
     * Browse all published articles (paginated), optionally filtered by category.
     */
    public function index(Request $request)
    {
        $activeCategory = $request->filled('category')
            ? KbCategory::active()->where('slug', $request->query('category'))->first()
            : null;

        $articles = KbArticle::published()
            ->when($activeCategory, fn ($q) => $q->inCategory($activeCategory->id))
            ->with('category')
            ->orderByDesc('published_at')
            ->paginate(12, ['id', 'ulid', 'title', 'slug', 'excerpt', 'views', 'helpful_count', 'published_at'])
            ->withQueryString();

        $categories = KbCategory::active()
            ->withCount(['articles' => fn ($q) => $q->published()])
            ->get(['id', 'slug', 'name', 'icon']);

        return Inertia::render('Addons/public-knowledge-base/Public/Articles', [
            'articles' => $articles,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'meta' => [
                'title' => ($activeCategory ? $activeCategory->name . ' — ' : '')
                    . addon_setting('public-knowledge-base', 'page_title', 'Help Center'),
                'description' => addon_setting('public-knowledge-base', 'page_description', ''),
            ],
        ]);
    }

    public function show(string $slug)
    {
        $article = KbArticle::published()
            ->where('slug', $slug)
            ->with('category')
            ->firstOrFail();

        DB::table('kb_articles')->where('id', $article->id)->increment('views');

        $related = app(KbSearchService::class)->getRelatedArticles($article);

        $sessionId = request()->session()->getId();
        $userVote = KbArticleVote::where('kb_article_id', $article->id)
            ->where('session_id', $sessionId)
            ->value('vote');

        return Inertia::render('Addons/public-knowledge-base/Public/Article', [
            'article' => $article,
            'related' => $related,
            'userVote' => $userVote,
            'meta' => [
                'title' => $article->meta_title ?: $article->title,
                'description' => $article->meta_desc ?: $article->excerpt,
            ],
        ]);
    }
}
