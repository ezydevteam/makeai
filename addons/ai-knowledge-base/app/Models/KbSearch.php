<?php

namespace Addons\AiKnowledgeBase\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class KbSearch extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'user_id',
        'query',
        'results_count',
        'was_answered',
        'article_ids',
        'created_at',
    ];

    protected $casts = [
        'results_count' => 'integer',
        'was_answered' => 'boolean',
        'article_ids' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
