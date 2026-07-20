<?php

namespace Addons\AiKnowledgeBase\Http\Controllers\Public;

use Addons\AiKnowledgeBase\Models\KbArticle;
use Addons\AiKnowledgeBase\Models\KbArticleVote;
use Addons\AiKnowledgeBase\Models\KbCategory;
use Addons\AiKnowledgeBase\Services\KbSearchService;
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

        $pageTitle = ($activeCategory ? $activeCategory->name . ' — ' : '')
            . addon_setting('ai-knowledge-base', 'page_title', 'Help Center');
        $description = addon_setting('ai-knowledge-base', 'page_description', '');

        return Inertia::render('Addons/ai-knowledge-base/Public/Articles', [
            'articles' => $articles,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            // Site-free — Articles.vue passes this to <Head :title>, which the global
            // callback suffixes with the site name.
            'meta' => [
                'title' => $pageTitle,
                'description' => $description,
            ],
            // Server-rendered head payload for app.blade.php. Without it this indexable
            // page fell back to the bare site name and emitted no canonical.
            'seo' => [
                'title' => document_title($pageTitle),
                'description' => $description,
                // Query-less: ?page/?category variants consolidate to one archive URL.
                'canonical' => route('addon.kb.public.articles'),
                'og' => [
                    'title' => $pageTitle,
                    'description' => $description,
                    'type' => 'website',
                    'url' => route('addon.kb.public.articles'),
                ],
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

        $pageTitle = $article->meta_title ?: $article->title;
        $description = $article->meta_desc ?: $article->excerpt;
        $canonical = route('addon.kb.public.article', $article->slug);

        return Inertia::render('Addons/ai-knowledge-base/Public/Article', [
            'article' => $article,
            'related' => $related,
            'userVote' => $userVote,
            // Site-free — Article.vue passes this to <Head :title>, which the global
            // callback suffixes with the site name.
            'meta' => [
                'title' => $pageTitle,
                'description' => $description,
            ],
            // Server-rendered head payload for app.blade.php. Without it this indexable
            // article fell back to the bare site name, the generic site description, and
            // emitted no canonical.
            'seo' => [
                'title' => document_title($pageTitle),
                'description' => $description,
                'canonical' => $canonical,
                'og' => [
                    'title' => $pageTitle,
                    'description' => $description,
                    'type' => 'article',
                    'url' => $canonical,
                    'published_time' => $article->published_at?->toAtomString(),
                    'modified_time' => $article->updated_at?->toAtomString(),
                ],
                // Server-side so crawlers see it without running JS; Article.vue built the
                // same TechArticle client-side only.
                'schemas' => [$this->articleSchema($article, $description, $canonical)],
            ],
        ]);
    }

    /**
     * TechArticle JSON-LD — the server-rendered twin of the schema Article.vue builds,
     * so the two must stay in step.
     */
    private function articleSchema(KbArticle $article, ?string $description, string $canonical): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'TechArticle',
            'headline' => $article->title,
            'description' => $description ?: '',
            'url' => $canonical,
            'datePublished' => $article->published_at?->toAtomString(),
            'dateModified' => $article->updated_at?->toAtomString(),
            'articleSection' => $article->category?->name,
        ], fn ($value) => $value !== null);
    }
}
