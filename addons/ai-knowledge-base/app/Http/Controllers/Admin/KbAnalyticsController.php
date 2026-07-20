<?php

namespace Addons\AiKnowledgeBase\Http\Controllers\Admin;

use Addons\AiKnowledgeBase\Models\KbArticle;
use Addons\AiKnowledgeBase\Models\KbSearch;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KbAnalyticsController extends Controller
{
    public function index()
    {
        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);
        $fourteenDaysAgo = $now->copy()->subDays(14);

        // 1. Searches Today
        $searchesTodayCurrent = KbSearch::whereDate('created_at', today())->count();
        $searchesTodayPrevious = KbSearch::whereDate('created_at', today()->subDays(7))->count();

        // 2. Searches 7 Days
        $searches7dCurrent = KbSearch::where('created_at', '>=', $sevenDaysAgo)->count();
        $searches7dPrevious = KbSearch::where('created_at', '>=', $fourteenDaysAgo)->where('created_at', '<', $sevenDaysAgo)->count();

        // 3. Answer Rate
        $answered7dCurrent = KbSearch::where('created_at', '>=', $sevenDaysAgo)->where('was_answered', true)->count();
        $answerRateCurrent = $searches7dCurrent > 0 ? (int) round(($answered7dCurrent / $searches7dCurrent) * 100) : 0;

        $answered7dPrevious = KbSearch::where('created_at', '>=', $fourteenDaysAgo)->where('created_at', '<', $sevenDaysAgo)->where('was_answered', true)->count();
        $answerRatePrevious = $searches7dPrevious > 0 ? (int) round(($answered7dPrevious / $searches7dPrevious) * 100) : 0;

        // 4. Published Articles — value is the all-time total; the comparison is a true
        // week-over-week on newly-published articles (by published_at), not the old
        // "total vs total-created-before-7d" ratio which was a cumulative growth rate
        // mislabeled as a period delta.
        $publishedCurrent = KbArticle::published()->count();
        $publishedThisWeek = KbArticle::published()->where('published_at', '>=', $sevenDaysAgo)->count();
        $publishedLastWeek = KbArticle::published()
            ->whereBetween('published_at', [$fourteenDaysAgo, $sevenDaysAgo])->count();

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

        return Inertia::render('Addons/ai-knowledge-base/Admin/Analytics', [
            'searches_today' => [
                'value' => $searchesTodayCurrent,
                'comparison' => $this->calculateComparison($searchesTodayCurrent, $searchesTodayPrevious),
            ],
            'searches_7d' => [
                'value' => $searches7dCurrent,
                'comparison' => $this->calculateComparison($searches7dCurrent, $searches7dPrevious),
            ],
            'answer_rate' => [
                'value' => $answerRateCurrent,
                'comparison' => $this->calculateComparison($answerRateCurrent, $answerRatePrevious),
            ],
            'published_count' => [
                'value' => $publishedCurrent,
                'comparison' => $this->calculateComparison($publishedThisWeek, $publishedLastWeek),
            ],
            'unanswered' => $unanswered,
            'top_queries' => $topQueries,
            'top_articles' => $topArticles,
            'embed_summary' => $embedSummary,
        ]);
    }

    private function calculateComparison(float|int $current, float|int $previous): array
    {
        if ($previous == 0) {
            return [
                'label' => $current == 0 ? '0%' : '+100%',
                'type' => $current == 0 ? 'neutral' : 'up',
            ];
        }

        $delta = (($current - $previous) / $previous) * 100;
        $rounded = (int) round(abs($delta));

        if ($rounded === 0) {
            return [
                'label' => '0%',
                'type' => 'neutral',
            ];
        }

        return [
            'label' => ($delta > 0 ? '+' : '-') . $rounded . '%',
            'type' => $delta > 0 ? 'up' : 'down',
        ];
    }
}
