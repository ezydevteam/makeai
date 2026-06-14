<?php

namespace Addons\PublicKnowledgeBase\Models;

use Illuminate\Database\Eloquent\Model;

class KbEmbedding extends Model
{
    protected $fillable = [
        'kb_article_id',
        'chunk_index',
        'chunk_text',
        'embedding',
        'token_count',
    ];

    protected $casts = [
        'chunk_index' => 'integer',
        'token_count' => 'integer',
        'embedding' => 'array',
    ];

    public function article()
    {
        return $this->belongsTo(KbArticle::class, 'kb_article_id');
    }
}
