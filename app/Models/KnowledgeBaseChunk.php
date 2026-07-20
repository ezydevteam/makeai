<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseChunk extends Model
{
    protected $fillable = [
        'document_id',
        'chunk_index',
        'text',
        'char_start',
        'char_end',
    ];

    protected $casts = [
        'chunk_index' => 'integer',
        'char_start' => 'integer',
        'char_end' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseDocument::class, 'document_id');
    }
}
