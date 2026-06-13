<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RagSession extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'tool_slug',
        'knowledge_base_id',
        'title',
        'source_meta',
        'status',
        'ingest_stage',
        'ingest_error',
        'saved_to_kb',
        'share_token',
    ];

    protected $casts = [
        'source_meta' => 'array',
        'saved_to_kb' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (RagSession $session) {
            if (! $session->id) {
                $session->id = (string) Str::ulid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class, 'knowledge_base_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(RagMessage::class, 'session_id');
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isEphemeral(): bool
    {
        return $this->knowledgeBase?->is_ephemeral ?? false;
    }
}
