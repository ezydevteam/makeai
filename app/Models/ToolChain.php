<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ToolChain extends Model
{
    protected $table = 'tool_chains';

    protected $fillable = ['user_id', 'name', 'steps', 'last_run_at', 'run_count'];

    protected function casts(): array
    {
        return ['steps' => 'array', 'last_run_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (ToolChain $chain) {
            if (empty($chain->ulid)) {
                $chain->ulid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ToolChainRun::class, 'chain_id');
    }
}
