<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VoEpisode extends Model
{
    protected $table = 'vo_episodes';

    protected $fillable = [
        'ulid',
        'vo_project_id',
        'user_id',
        'music_track_id',
        'title',
        'episode_number',
        'season_number',
        'script',
        'segments',
        'status',
        'provider',
        'voice_id',
        'music_volume',
        'file_path',
        'file_url',
        'file_size_bytes',
        'waveform_path',
        'waveform_url',
        'duration_seconds',
        'format',
        'transcript_srt',
        'transcript_vtt',
        'credits_deducted',
        'error_message',
        'share_token',
        'share_enabled',
        'published_at',
        'expires_at',
    ];

    protected $casts = [
        'segments' => 'array',
        'music_volume' => 'float',
        'duration_seconds' => 'integer',
        'file_size_bytes' => 'integer',
        'credits_deducted' => 'float',
        'share_enabled' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $appends = ['duration_label', 'can_retry', 'is_published'];

    protected static function booted(): void
    {
        static::creating(function (self $episode) {
            if (empty($episode->ulid)) {
                $episode->ulid = (string) Str::ulid();
            }
            if (empty($episode->share_token)) {
                $episode->share_token = Str::random(64);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function project()
    {
        return $this->belongsTo(VoProject::class, 'vo_project_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function musicTrack()
    {
        return $this->belongsTo(VoMusicTrack::class, 'music_track_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'completed')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getDurationLabelAttribute(): string
    {
        if (! $this->duration_seconds) {
            return '0:00';
        }

        $minutes = intdiv($this->duration_seconds, 60);
        $seconds = $this->duration_seconds % 60;

        return $minutes . ':' . str_pad((string) $seconds, 2, '0', STR_PAD_LEFT);
    }

    public function getCanRetryAttribute(): bool
    {
        return $this->status === 'failed';
    }

    public function getIsPublishedAttribute(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }
}
