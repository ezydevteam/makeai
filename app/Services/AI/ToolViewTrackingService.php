<?php

namespace App\Services\AI;

use App\Models\AiTool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * ToolViewTrackingService — track tool page views via Redis with hourly DB flush.
 * Falls back to cache/database if Redis is unavailable.
 */
class ToolViewTrackingService
{
    private const REDIS_PREFIX = 'tool_views:';
    private const CACHE_PREFIX = 'tool_views_fallback:';

    /**
     * Increment view counter in Redis for a tool slug.
     * Falls back to cache if Redis is unavailable.
     */
    public function record(string $toolSlug): void
    {
        try {
            $key = self::REDIS_PREFIX . $toolSlug;
            Redis::incr($key);
        } catch (\Exception $e) {
            Log::warning('Redis unavailable for view tracking, using cache fallback', [
                'slug' => $toolSlug,
                'error' => $e->getMessage(),
            ]);
            // Fallback: use Laravel cache (file/database/array driver)
            $cacheKey = self::CACHE_PREFIX . $toolSlug;
            $current = (int) Cache::get($cacheKey, 0);
            Cache::put($cacheKey, $current + 1, now()->addHours(2));
        }
    }

    /**
     * Get current view count from Redis for a tool slug.
     * Falls back to cache if Redis is unavailable.
     */
    public function currentViews(string $toolSlug): int
    {
        try {
            return (int) Redis::get(self::REDIS_PREFIX . $toolSlug);
        } catch (\Exception $e) {
            $cacheKey = self::CACHE_PREFIX . $toolSlug;
            return (int) Cache::get($cacheKey, 0);
        }
    }

    /**
     * Flush Redis view counters to tool_page_views table and update ai_tools.views_count.
     * Called hourly by scheduler. Also flushes cache fallback counters.
     */
    public function flushToDatabase(): int
    {
        $today = now()->toDateString();
        $flushed = 0;

        // Flush Redis counters
        try {
            $keys = Redis::keys(self::REDIS_PREFIX . '*');

            foreach ($keys as $key) {
                $slug = str_replace(self::REDIS_PREFIX, '', $key);
                $count = (int) Redis::get($key);

                if ($count <= 0) {
                    continue;
                }

                $result = $this->flushCountToDatabase($slug, $count, $today);
                if ($result) {
                    // Reset Redis counter
                    Redis::set($key, 0);
                    $flushed++;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Redis unavailable during flush, skipping Redis keys', [
                'error' => $e->getMessage(),
            ]);
        }

        // Flush cache fallback counters
        $flushed += $this->flushCacheFallbackToDatabase($today);

        return $flushed;
    }

    /**
     * Flush a single slug's view count to the database.
     */
    private function flushCountToDatabase(string $slug, int $count, string $today): bool
    {
        // Upsert into tool_page_views
        \DB::table('tool_page_views')->updateOrInsert(
            ['tool_slug' => $slug, 'viewed_date' => $today],
            ['views_count' => \DB::raw("views_count + {$count}")]
        );

        // Update denormalized count on ai_tools
        $affected = AiTool::where('slug', $slug)->increment('views_count', $count);

        if ($affected === 0) {
            Log::warning('ToolViewTrackingService: increment returned 0 — tool may not exist', [
                'slug' => $slug,
                'count' => $count,
            ]);
        }

        return true;
    }

    /**
     * Flush cache-based fallback counters to database.
     */
    private function flushCacheFallbackToDatabase(string $today): int
    {
        $flushed = 0;

        // Get all tool slugs that might have fallback counters
        $toolSlugs = AiTool::pluck('slug');

        foreach ($toolSlugs as $slug) {
            $cacheKey = self::CACHE_PREFIX . $slug;
            $count = (int) Cache::get($cacheKey, 0);

            if ($count <= 0) {
                continue;
            }

            $this->flushCountToDatabase($slug, $count, $today);
            Cache::forget($cacheKey);
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
