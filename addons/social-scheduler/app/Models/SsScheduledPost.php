<?php

namespace Addons\SocialScheduler\Models;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SsScheduledPost extends Model
{
    use HasFactory;
    protected $fillable = [
        'ulid', 'user_id', 'ss_campaign_id', 'title', 'caption', 'hashtags',
        'platforms', 'status', 'post_type', 'scheduled_at', 'published_at',
        'is_rss_auto', 'rss_feed_id', 'approved_by', 'approved_at',
        'rejection_reason', 'first_comment', 'platform_overrides',
    ];

    protected $appends = ['status_label', 'is_overdue'];

    protected function casts(): array
    {
        return [
            'platforms' => 'array',
            'platform_overrides' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_rss_auto' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $post) {
            $post->ulid = (string) Str::ulid();
        });

        static::saving(function (self $post) {
            if ($post->isDirty('status') && $post->status === 'scheduled') {
                if (
                    addon_setting('social-scheduler', 'approval_required', false)
                    && ! $post->approved_at
                ) {
                    $post->status = 'pending_approval';
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function campaign()
    {
        return $this->belongsTo(SsCampaign::class, 'ss_campaign_id');
    }

    public function rssFeed()
    {
        return $this->belongsTo(SsRssFeed::class, 'rss_feed_id');
    }

    public function media()
    {
        return $this->hasMany(SsPostMedia::class, 'ss_scheduled_post_id');
    }

    public function carouselSlides()
    {
        return $this->hasMany(SsCarouselSlide::class, 'ss_scheduled_post_id');
    }

    public function postPlatforms()
    {
        return $this->hasMany(SsPostPlatform::class, 'ss_scheduled_post_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            'draft' => 'Draft',
            'pending_approval' => 'Pending Approval',
            'scheduled' => 'Scheduled',
            'publishing' => 'Publishing',
            'published' => 'Published',
            'partial' => 'Partially Published',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
        ][$this->status] ?? $this->status;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->scheduled_at
            && $this->scheduled_at->isPast()
            && $this->status === 'scheduled';
    }

    public function scopeDue($query)
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now());
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }

    public function scopeForCalendar($query, $start, $end)
    {
        return $query->whereBetween('scheduled_at', [$start, $end]);
    }
}
