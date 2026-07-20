<?php

namespace Addons\AiKnowledgeBase\Models;

use Illuminate\Database\Eloquent\Model;

class KbEmbedding extends Model
{
    protected $fillable = [
        'kb_article_id',
        'chunk_index',
        'chunk_text',
        'embedding',
    ];

    protected $casts = [
        'chunk_index' => 'integer',
        'embedding' => 'array',
    ];

    public function article()
    {
        return $this->belongsTo(KbArticle::class, 'kb_article_id');
    }
}
