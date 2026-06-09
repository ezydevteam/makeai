<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\DB;

/**
 * ToolSlugHistoryService — tracks renamed slugs and resolves 301 redirects.
 */
class ToolSlugHistoryService
{
    /**
     * Record a slug change for 301 redirect support.
     */
    public function record(string $oldSlug, string $newSlug, string $modelType = 'ai_tool'): void
    {
        DB::table('tool_slug_history')->insert([
            'old_slug' => $oldSlug,
            'new_slug' => $newSlug,
            'model_type' => $modelType,
            'changed_at' => now(),
        ]);
    }

    /**
     * Find the new slug for a given old slug. Returns null if no redirect exists.
     */
    public function resolve(string $oldSlug, string $modelType = 'ai_tool'): ?string
    {
        $record = DB::table('tool_slug_history')
            ->where('old_slug', $oldSlug)
            ->where('model_type', $modelType)
            ->latest('changed_at')
            ->first();

        return $record?->new_slug;
    }

    /**
     * Check if slug has been changed before (supports redirect chains up to 5 hops).
     */
    public function resolveChain(string $slug, string $modelType = 'ai_tool', int $maxHops = 5): ?string
    {
        $current = $slug;
        $seen = [$slug];

        for ($i = 0; $i < $maxHops; $i++) {
            $next = $this->resolve($current, $modelType);
            if (! $next || in_array($next, $seen)) {
                return $current !== $slug ? $current : null;
            }
            $seen[] = $next;
            $current = $next;
        }

        return $current !== $slug ? $current : null;
    }
}
