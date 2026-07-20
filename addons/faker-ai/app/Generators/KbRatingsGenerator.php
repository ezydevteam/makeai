<?php

namespace Addons\FakerAi\Generators;

use Addons\FakerAi\Models\FakerBatch;
use Addons\AiKnowledgeBase\Models\KbArticle;

/**
 * Fake "was this helpful?" ratings on Knowledge Base articles. Only offered when the
 * ai-knowledge-base addon is active. Splits each article's share into helpful vs
 * not-helpful with a positive skew; rollback subtracts both back.
 */
class KbRatingsGenerator extends AbstractGenerator
{
    public function type(): string
    {
        return 'kb-ratings';
    }

    public function label(): string
    {
        return 'Knowledge Base Helpful Ratings';
    }

    public function group(): string
    {
        return 'Knowledge Base';
    }

    public function usesAi(): bool
    {
        return false;
    }

    public function isAvailable(): bool
    {
        return is_addon_active('ai-knowledge-base')
            && class_exists(KbArticle::class);
    }

    public function requiresTarget(): bool
    {
        return true;
    }

    public function targets(): array
    {
        $articles = KbArticle::published()
            ->orderByDesc('published_at')
            ->limit(200)
            ->get(['ulid', 'title'])
            ->map(fn (KbArticle $a) => ['value' => $a->ulid, 'label' => $a->title])
            ->all();

        return array_merge([['value' => '*', 'label' => 'Spread across all published articles']], $articles);
    }

    public function generate(FakerBatch $batch): int
    {
        $articles = $this->resolveArticles($batch);
        if ($articles->isEmpty()) {
            return 0;
        }

        $shares = $this->distribute($batch->requested_count, $articles->count());
        $applied = 0;

        foreach ($articles as $i => $article) {
            $votes = $shares[$i];
            if ($votes <= 0) {
                continue;
            }

            // Positive skew: 70-95% of votes are "helpful".
            $helpful = (int) round($votes * (random_int(70, 95) / 100));
            $notHelpful = $votes - $helpful;

            if ($helpful > 0) {
                KbArticle::whereKey($article->id)->increment('helpful_count', $helpful);
                $batch->recordIncrement($article, 'helpful_count', $helpful);
            }
            if ($notHelpful > 0) {
                KbArticle::whereKey($article->id)->increment('not_helpful_count', $notHelpful);
                $batch->recordIncrement($article, 'not_helpful_count', $notHelpful);
            }

            $applied += $votes;
        }

        return $applied;
    }

    private function distribute(int $total, int $buckets): array
    {
        if ($buckets <= 0) {
            return [];
        }

        $base = intdiv($total, $buckets);
        $remainder = $total % $buckets;

        $shares = [];
        for ($i = 0; $i < $buckets; $i++) {
            $shares[$i] = $base + ($i < $remainder ? 1 : 0);
        }

        return $shares;
    }

    private function resolveArticles(FakerBatch $batch)
    {
        return $this->applyTargets(KbArticle::published(), $batch, 'ulid')
            ->get(['id', 'ulid'])
            ->values();
    }
}
