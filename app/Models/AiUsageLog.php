<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'model', 'type', 'tool_slug',
        'input_tokens', 'output_tokens', 'cost_usd',
        'credits_used', 'request_id', 'response_time_ms',
        'status', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'cost_usd' => 'decimal:6',
            'credits_used' => 'decimal:2',
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
