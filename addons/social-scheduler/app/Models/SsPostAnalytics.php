<?php

namespace Addons\SocialScheduler\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SsPostAnalytics extends Model
{
    use HasFactory;
    protected $fillable = [
        'ss_post_platform_id', 'platform', 'impressions', 'reach',
        'likes', 'comments', 'shares', 'saves', 'clicks',
        'video_views', 'engagement_rate', 'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
            'engagement_rate' => 'float',
        ];
    }

    public function postPlatform()
    {
        return $this->belongsTo(SsPostPlatform::class, 'ss_post_platform_id');
    }
}
