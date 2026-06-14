<?php

namespace Addons\PublicKnowledgeBase\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class KbArticleVote extends Model
{
    protected $fillable = [
        'kb_article_id',
        'session_id',
        'user_id',
        'vote',
    ];

    protected $casts = [
        'vote' => 'integer',
    ];

    public function article()
    {
        return $this->belongsTo(KbArticle::class, 'kb_article_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
