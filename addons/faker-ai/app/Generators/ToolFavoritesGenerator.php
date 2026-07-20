<?php

namespace Addons\FakerAi\Generators;

use Addons\FakerAi\Models\FakerBatch;
use App\Models\AiTool;
use App\Models\Favorite;
use App\Models\User;

/**
 * Fake favorites for AI tools — the heart/count shown on tool cards and the tool page.
 *
 * No AI: a favorite is just a user→tool link, and the favouriting user is never displayed
 * publicly (FavoriteButton renders a count only), so there is no content worth paying a model
 * to write.
 *
 * Favorites are attached to FAKE users, never to real ones: `favorites` is what powers a
 * member's own "My Favorites" list, so seeding a real account would put tools a customer never
 * picked into their dashboard.
 *
 * The `favorites` table is UNIQUE(user_id, favoriteable_type, favoriteable_id), so a tool needs
 * one distinct user per favorite — but the SAME user may favorite many different tools. So this
 * mints a pool sized to the largest per-tool share and reuses it across tools, rather than one
 * user per row: seeding 500 favorites across 10 tools costs 50 users, not 500.
 */
class ToolFavoritesGenerator extends AbstractGenerator
{
    public function type(): string
    {
        return 'tool-favorites';
    }

    public function label(): string
    {
        return 'Tool Favorites';
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

        // requested_count is the TOTAL number of favorites to distribute across the selection.
        $shares = $this->distribute($batch->requested_count, $tools->count());
        $poolSize = (int) max($shares);
        if ($poolSize <= 0) {
            return 0;
        }

        $pool = $this->buildUserPool($batch, $poolSize);
        $applied = 0;

        foreach ($tools as $i => $tool) {
            for ($n = 0; $n < $shares[$i]; $n++) {
                $favorite = Favorite::create([
                    'user_id' => $pool[$n]->id,
                    'favoriteable_type' => AiTool::class,
                    'favoriteable_id' => $tool->id,
                ]);

                // The tool page reads a live favorites()->count(), so no counter to bump —
                // but backdate the row so it doesn't look like every favorite landed at once.
                $date = $this->backdate();
                $favorite->forceFill(['created_at' => $date, 'updated_at' => $date])->save();

                $batch->recordInsert($favorite);
                $applied++;
            }
        }

        return $applied;
    }

    /**
     * A reusable set of fake users, each recorded on the batch so rollback removes them.
     *
     * @return array<int,User>
     */
    private function buildUserPool(FakerBatch $batch, int $size): array
    {
        $pool = [];
        for ($i = 0; $i < $size; $i++) {
            $pool[] = $this->createFakeUser($batch, '', $this->backdate());
        }

        return $pool;
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
