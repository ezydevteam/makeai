<?php

namespace Addons\SocialScheduler\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SsPostPlatform extends Model
{
    use HasFactory;
    protected $fillable = [
        'ss_scheduled_post_id', 'ss_social_account_id', 'platform',
        'status', 'external_post_id', 'external_post_url',
        'error_message', 'published_at', 'attempt_count',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'attempt_count' => 'integer',
        ];
    }

    public function scheduledPost()
    {
        return $this->belongsTo(SsScheduledPost::class, 'ss_scheduled_post_id');
    }

    public function socialAccount()
    {
        return $this->belongsTo(SsSocialAccount::class, 'ss_social_account_id');
    }

    public function analytics()
    {
        return $this->hasOne(SsPostAnalytics::class, 'ss_post_platform_id');
    }

    public function getExternalUrlAttribute($value): ?string
    {
        return $value;
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRetryable($query)
    {
        return $query->where('status', 'failed')
            ->where('attempt_count', '<', 3);
    }
}
