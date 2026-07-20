<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PlaygroundService
{
    /**
     * How long a shared snapshot stays retrievable.
     */
    private const SHARE_TTL_DAYS = 7;

    /**
     * Snapshots go through the cache, not Redis directly: Redis is optional on an
     * install (this one runs the database cache/queue driver), and an unguarded
     * Redis::setex here 500s the Share button on any install without it.
     */
    public function share(array $snapshot): string
    {
        $uuid = (string) Str::uuid();

        Cache::put(
            $this->cacheKey($uuid),
            array_merge($snapshot, ['created_at' => now()->toIso8601String()]),
            now()->addDays(self::SHARE_TTL_DAYS),
        );

        return $uuid;
    }

    public function getShare(string $uuid): ?array
    {
        $snapshot = Cache::get($this->cacheKey($uuid));

        return is_array($snapshot) ? $snapshot : null;
    }

    private function cacheKey(string $uuid): string
    {
        return "playground_share:{$uuid}";
    }
}
