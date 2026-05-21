<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Document extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'content',
        'tool_slug',
        'word_count',
        'folder_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoriteable');
    }
}
