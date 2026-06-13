<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RagMessage extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'session_id',
        'role',
        'content',
        'sources',
        'input_tokens',
        'output_tokens',
        'credits_used',
        'created_at',
    ];

    protected $casts = [
        'sources' => 'array',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'credits_used' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (RagMessage $message) {
            if (! $message->id) {
                $message->id = (string) Str::ulid();
            }
            if (! $message->created_at) {
                $message->created_at = now();
            }
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(RagSession::class, 'session_id');
    }
}
