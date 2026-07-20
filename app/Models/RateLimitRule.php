<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Global rate-limit tier matrix — one row per (category, tier).
 *
 * Replaces the 48 flat `rl_{category}_{tier}_{max|window}` rows that previously
 * lived in the `settings` table. The whole matrix is loaded through a single cached
 * map (see map()), so a request that checks several tiers pays one cache lookup
 * instead of one per key.
 *
 * Per-user exceptions still live in {@see UserRateLimitOverride}; this table holds
 * only the global defaults an admin edits on the Rate Limits screen.
 */
class RateLimitRule extends Model
{
    protected $fillable = ['category', 'tier', 'max_attempts', 'window_seconds'];

    protected $casts = [
        'max_attempts' => 'integer',
        'window_seconds' => 'integer',
    ];

    private const CACHE_KEY = 'rate_limit_rules:map';

    /**
     * The full matrix as [category => [tier => ['max_attempts' => int, 'window_seconds' => int]]],
     * cached forever and invalidated on any write.
     */
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::all()
                ->groupBy('category')
                ->map(function ($rows) {
                    return $rows->keyBy('tier')->map(function ($rule) {
                        return [
                            'max_attempts' => $rule->max_attempts,
                            'window_seconds' => $rule->window_seconds,
                        ];
                    })->toArray();
                })
                ->toArray();
        });
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }
}
