<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    protected $appends = [
        'total_tokens',
    ];

    protected $fillable = [
        'user_id', 'provider', 'model', 'type', 'tool_slug',
        'input_tokens', 'output_tokens', 'cost_usd',
        'credits_used', 'response_time_ms',
        'aggregated_at', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'cost_usd' => 'decimal:6',
            'credits_used' => 'decimal:2',
            'aggregated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Total tokens for this request.
     */
    public function getTotalTokensAttribute(): int
    {
        return $this->input_tokens + $this->output_tokens;
    }
}
