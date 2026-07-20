<?php

namespace Addons\SocialScheduler\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SsRssFeed extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'url', 'title', 'platforms', 'caption_prompt',
        'status', 'last_item_guid',
    ];

    protected function casts(): array
    {
        return [
            'platforms' => 'array',
            'last_polled_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scheduledPosts()
    {
        return $this->hasMany(SsScheduledPost::class, 'rss_feed_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
