<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiModel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'provider',
        'is_active',
        'cost_input_1k',
        'cost_output_1k',
        'credits_per_1k',
        'credits_auto',
        'max_tokens',
        'rate_limit_per_min',
        'type',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cost_input_1k' => 'decimal:8',
        'cost_output_1k' => 'decimal:8',
        'credits_auto' => 'boolean',
        'meta' => 'array',
    ];

    /**
     * Scope to only include active models.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to only include a specific type of models.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Resolve the model row used for pricing and limits.
     *
     * Providers return fully-qualified names (e.g. "gpt-4o-mini-2024-07-18") that
     * don't match the stored slug ("gpt-4o-mini"). Fall back to the longest stored
     * slug that prefixes the returned name so pricing/limits never silently drop to
     * the generic fallback for a known model. Single source of truth shared by
     * TokenGuard (billing) and PromptBuilder (estimate + max-token clamp).
     */
    public static function resolveForPricing(string $slug): ?self
    {
        $exact = static::where('slug', $slug)->first();
        if ($exact) {
            return $exact;
        }

        return static::all()
            ->filter(fn (self $candidate) => str_starts_with($slug, $candidate->slug))
            ->sortByDesc(fn (self $candidate) => strlen($candidate->slug))
            ->first();
    }
}
