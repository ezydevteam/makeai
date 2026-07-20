<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBaseDocument extends Model
{
    protected $fillable = [
        'knowledge_base_id',
        'user_id',
        'filename',
        'filesize',
        'char_count',
        'chunk_count',
        'status',
        'deleted_at',
    ];

    protected $casts = [
        'filesize' => 'integer',
        'char_count' => 'integer',
        'chunk_count' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeBaseChunk::class, 'document_id');
    }
}
