<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ToolChainRun extends Model
{
    protected $table = 'tool_chain_runs';

    protected $fillable = [
        'chain_id', 'user_id', 'status', 'step_outputs',
        'total_tokens', 'total_credits', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'step_outputs' => 'array',
            'total_credits' => 'decimal:4',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ToolChainRun $run) {
            if (empty($run->ulid)) {
                $run->ulid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function chain(): BelongsTo
    {
        return $this->belongsTo(ToolChain::class, 'chain_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
