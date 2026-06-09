<?php

namespace App\Services\AI;

use App\Models\AiTool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * ToolViewTrackingService — track tool page views via Redis with hourly DB flush.
 */
class ToolViewTrackingService
{
    private const REDIS_PREFIX = 'tool_views:';

    /**
     * Increment view counter in Redis for a tool slug.
     */
    public function record(string $toolSlug): void
    {
        $key = self::REDIS_PREFIX . $toolSlug;
        Redis::incr($key);
    }

    /**
     * Get current view count from Redis for a tool slug.
     */
    public function currentViews(string $toolSlug): int
    {
        return (int) Redis::get(self::REDIS_PREFIX . $toolSlug);
    }

    /**
     * Flush Redis view counters to tool_page_views table and update ai_tools.views_count.
     * Called hourly by scheduler.
     */
    public function flushToDatabase(): int
    {
        $today = now()->toDateString();
        $keys = Redis::keys(self::REDIS_PREFIX . '*');
        $flushed = 0;

        foreach ($keys as $key) {
            $slug = str_replace(self::REDIS_PREFIX, '', $key);
            $count = (int) Redis::get($key);

            if ($count <= 0) {
                continue;
            }

            // Upsert into tool_page_views
            \DB::table('tool_page_views')->updateOrInsert(
                ['tool_slug' => $slug, 'viewed_date' => $today],
                ['views_count' => \DB::raw("views_count + {$count}")]
            );

            // Update denormalized count on ai_tools
            AiTool::where('slug', $slug)->increment('views_count', $count);

            // Reset Redis counter
            Redis::set($key, 0);
            $flushed++;
        }

        return $flushed;
    }

    /**
     * Get total views for a tool (all time, from ai_tools.views_count).
     */
    public function totalViews(string $toolSlug): int
    {
        return (int) AiTool::where('slug', $toolSlug)->value('views_count');
    }
}
