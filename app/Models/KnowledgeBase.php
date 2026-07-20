<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBase extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_public',
        'is_ephemeral',
        'source_tool',
        'expires_at',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_ephemeral' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeBaseDocument::class);
    }

    public function chunks(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            KnowledgeBaseChunk::class,
            KnowledgeBaseDocument::class,
            'knowledge_base_id',
            'document_id',
        );
    }

    public function ragSessions(): HasMany
    {
        return $this->hasMany(RagSession::class, 'knowledge_base_id');
    }

    public function scopePermanent($query)
    {
        return $query->where('is_ephemeral', false);
    }

    public function scopeEphemeral($query)
    {
        return $query->where('is_ephemeral', true);
    }
}
