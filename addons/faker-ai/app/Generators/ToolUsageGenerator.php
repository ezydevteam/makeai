<?php

namespace Addons\FakerAi\Generators;

use Addons\FakerAi\Models\FakerBatch;
use App\Models\AiTool;
use App\Services\AI\ToolCatalogCacheService;

/**
 * Fake usage/view counters for AI tools. No AI — it just bumps usage_count (and a proportional
 * views_count) so tool cards and "popular tools" lists look alive. The bump amount is recorded
 * so rollback subtracts exactly what was added.
 */
class ToolUsageGenerator extends AbstractGenerator
{
    public function type(): string
    {
        return 'tool-usage';
    }

    public function label(): string
    {
        return 'Tool Usage';
    }

    public function group(): string
    {
        return 'Tools';
    }

    public function usesAi(): bool
    {
        return false;
    }

    public function requiresTarget(): bool
    {
        return true;
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

        // requested_count is the TOTAL usage to distribute across the selected tools.
        $shares = $this->distribute($batch->requested_count, $tools->count());
        $applied = 0;

        foreach ($tools as $i => $tool) {
            $uses = $shares[$i];
            if ($uses <= 0) {
                continue;
            }

            // Views run ahead of usage on a real tool — most visitors look, some run it.
            $views = $uses + random_int($uses, $uses * 4);

            AiTool::whereKey($tool->id)->increment('usage_count', $uses);
            AiTool::whereKey($tool->id)->increment('views_count', $views);

            $batch->recordIncrement($tool, 'usage_count', $uses);
            $batch->recordIncrement($tool, 'views_count', $views);

            ToolCatalogCacheService::invalidateForTool($tool);
            $applied += $uses;
        }

        return $applied;
    }

    public function rollback(FakerBatch $batch): void
    {
        // Grab the affected tools before the ledger is replayed, so we can bust their caches
        // after the counters come back down.
        $toolIds = $batch->records()
            ->where('subject_type', AiTool::class)
            ->pluck('subject_id')
            ->unique();

        parent::rollback($batch);

        AiTool::whereKey($toolIds->all())->get()->each(
            fn (AiTool $tool) => ToolCatalogCacheService::invalidateForTool($tool)
        );
    }

    /**
     * Split a total into $buckets near-even integer shares (remainder spread over the first few).
     *
     * @return array<int,int>
     */
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

    private function resolveTools(FakerBatch $batch)
    {
        return $this->applyTargets(AiTool::query()->where('is_active', true), $batch, 'slug')
            ->get(['id', 'slug', 'name'])
            ->values();
    }
}
