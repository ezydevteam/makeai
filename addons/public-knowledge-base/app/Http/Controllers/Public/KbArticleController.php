<?php

namespace Addons\PublicKnowledgeBase\Http\Controllers\Public;

use Addons\PublicKnowledgeBase\Models\KbArticle;
use Addons\PublicKnowledgeBase\Models\KbArticleVote;
use Addons\PublicKnowledgeBase\Services\KbSearchService;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KbArticleController extends Controller
{
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
