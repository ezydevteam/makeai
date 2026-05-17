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
        'max_tokens',
        'rate_limit_per_min',
        'type',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cost_input_1k' => 'decimal:8',
        'cost_output_1k' => 'decimal:8',
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
}
