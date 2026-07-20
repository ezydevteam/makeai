<?php

namespace Addons\FakerAi\Generators;

use Addons\FakerAi\Models\FakerBatch;
use App\Models\AiTool;
use App\Models\ToolReview;

/**
 * Fake reviews for AI tools. Each review gets its own fake author (the tool_reviews table is
 * UNIQUE on user_id+tool_slug), so a review and its author both live and die with the batch.
 *
 * ToolReview's own saved/deleted model hooks recompute the tool's avg_rating/review_count and
 * bust its cache, so both generation and rollback keep the tool's stats correct with no extra
 * work here.
 */
class ToolReviewsGenerator extends AbstractGenerator
{
    public function type(): string
    {
        return 'tool-reviews';
    }

    public function label(): string
    {
        return 'Tool Reviews';
    }

    public function group(): string
    {
        return 'Tools';
    }

    public function requiresTarget(): bool
    {
        return true;
    }

    public function optionFields(): array
    {
        return [$this->ratingOptionField()];
    }

    public function targets(): array
    {
        $tools = AiTool::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['slug', 'name'])
            ->map(fn (AiTool $t) => ['value' => $t->slug, 'label' => $t->name])
            ->all();

        return array_merge([['value' => '*', 'label' => 'Spread across all tools']], $tools);
    }

    public function generate(FakerBatch $batch): int
    {
        $tools = $this->resolveTools($batch);
        if ($tools->isEmpty()) {
            return 0;
        }

        $band = $this->ratingBand($batch);

        $items = $this->content->generate(
            noun: 'AI tool review',
            fields: [
                'author_name' => 'the full name of the reviewer',
                'rating' => $this->ratingFieldHint($band),
                'comment' => 'a 1-3 sentence review of using the tool, concrete and varied',
            ],
            count: $batch->requested_count,
            style: $this->style($batch, [
                'context' => trim((string) settings('site_name', config('app.name', ''))).' — a suite of AI tools',
            ]),
            batch: $batch,
        );

        $created = 0;
        foreach ($items as $i => $item) {
            $comment = trim((string) ($item['comment'] ?? ''));
            if ($comment === '') {
                continue;
            }

            // Round-robin across the selected tools so a "spread" run touches them evenly.
            $tool = $tools[$i % $tools->count()];
            $date = $this->backdate();
            $author = $this->createFakeUser($batch, (string) ($item['author_name'] ?? ''), $date);

            $review = new ToolReview([
                'tool_slug' => $tool->slug,
                'user_id' => $author->id,
                'rating' => $this->pickRating($item['rating'] ?? null, $band),
                'comment' => $comment,
                'is_approved' => true,
                'helpful_count' => random_int(0, 25),
            ]);
            $review->created_at = $date;
            $review->updated_at = $date;
            $review->save();

            $batch->recordInsert($review);
            $created++;
        }

        return $created;
    }

    private function resolveTools(FakerBatch $batch)
    {
        return $this->applyTargets(AiTool::query()->where('is_active', true), $batch, 'slug')
            ->get(['slug', 'name'])
            ->values();
    }
}
