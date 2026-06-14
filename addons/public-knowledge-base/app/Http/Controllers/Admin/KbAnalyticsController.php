<?php

namespace Addons\PublicKnowledgeBase\Http\Controllers\Admin;

use Addons\PublicKnowledgeBase\Models\KbArticle;
use Addons\PublicKnowledgeBase\Models\KbSearch;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KbAnalyticsController extends Controller
{
    public function index()
    {
        $searchesToday = KbSearch::whereDate('created_at', today())->count();
        $searches7d = KbSearch::where('created_at', '>=', now()->subDays(7))->count();
        $answered7d = KbSearch::where('created_at', '>=', now()->subDays(7))->where('was_answered', true)->count();
        $answerRate = $searches7d > 0 ? round(($answered7d / $searches7d) * 100) : 0;

        $unanswered = KbSearch::where('was_answered', false)
            ->latest()
            ->limit(20)
            ->pluck('query');

        $topQueries = KbSearch::groupBy('query')
            ->select('query', DB::raw('count(*) as count'))
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->map(fn ($r) => ['query' => $r->query, 'count' => $r->count]);

        $topArticles = KbArticle::published()
            ->orderByDesc('views')
            ->limit(10)
            ->get(['id', 'title', 'views', 'helpful_count', 'not_helpful_count']);

        $embedSummary = KbArticle::groupBy('embed_status')
            ->select('embed_status', DB::raw('count(*) as count'))
            ->pluck('count', 'embed_status')
            ->toArray();

        return Inertia::render('Addons/public-knowledge-base/Admin/Analytics', [
            'searches_today' => $searchesToday,
            'searches_7d' => $searches7d,
            'answer_rate' => $answerRate,
            'unanswered' => $unanswered,
            'top_queries' => $topQueries,
            'top_articles' => $topArticles,
            'embed_summary' => $embedSummary,
            'published_count' => KbArticle::published()->count(),
        ]);
    }
}
