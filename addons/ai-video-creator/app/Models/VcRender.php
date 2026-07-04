<?php

namespace Addons\AiVideoCreator\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VcRender extends Model
{
    protected $fillable = [
        'ulid', 'user_id', 'vc_project_id', 'type', 'status', 'provider',
        'provider_job_id', 'poll_attempts', 'title', 'prompt', 'script',
        'duration_seconds', 'aspect_ratio', 'resolution', 'provider_settings',
        'input_media_path', 'file_path', 'file_url', 'file_size_bytes',
        'thumbnail_path', 'thumbnail_url', 'duration_actual', 'share_enabled',
        'share_token', 'credits_deducted', 'error_message', 'metadata',
        'completed_at', 'expires_at',
    ];

    protected $appends = ['status_label', 'type_label', 'can_retry', 'is_expired'];

    protected function casts(): array
    {
        return [
            'provider_settings' => 'array',
            'metadata' => 'array',
            'share_enabled' => 'boolean',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'credits_deducted' => 'float',
            'duration_seconds' => 'integer',
            'duration_actual' => 'integer',
            'poll_attempts' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $render) {
            $render->ulid = (string) Str::ulid();
            $render->share_token = Str::random(64);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(VcProject::class, 'vc_project_id');
    }

    public function subtitles()
    {
        return $this->hasMany(VcSubtitle::class, 'vc_render_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            'queued' => 'Queued',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
        ][$this->status] ?? (string) $this->status;
    }

    public function getTypeLabelAttribute(): string
    {
        return [
            'text_to_video' => 'Text to Video',
            'image_to_video' => 'Image to Video',
            'avatar_video' => 'AI Avatar',
            'slideshow' => 'Slideshow',
        ][$this->type] ?? (string) $this->type;
    }

    public function getCanRetryAttribute(): bool
    {
        return $this->status === 'failed';
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeProcessing($query)
    {
        return $query->whereIn('status', ['queued', 'processing']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed')->whereNotNull('file_path');
    }

    public function scopePendingPoll($query)
    {
        $max = (int) addon_setting('ai-video-creator', 'max_poll_attempts', 20);

        return $query->where('status', 'processing')
            ->whereNotNull('provider_job_id')
            ->where('poll_attempts', '<', $max);
    }
}
