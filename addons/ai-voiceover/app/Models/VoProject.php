<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VoProject extends Model
{
    protected $table = 'vo_projects';

    protected $fillable = [
        'ulid',
        'user_id',
        'title',
        'type',
        'description',
        'cover_art_path',
        'cover_art_url',
        'podcast_author',
        'podcast_category',
        'podcast_language',
        'podcast_explicit',
        'rss_token',
        'rss_enabled',
        'total_duration',
        'episode_count',
    ];

    protected $casts = [
        'podcast_explicit' => 'boolean',
        'rss_enabled' => 'boolean',
        'total_duration' => 'integer',
        'episode_count' => 'integer',
    ];

    protected $appends = ['cover_art_url_resolved', 'rss_url'];

    protected static function booted(): void
    {
        static::creating(function (self $project) {
            if (empty($project->ulid)) {
                $project->ulid = (string) Str::ulid();
            }
            if (empty($project->rss_token)) {
                $project->rss_token = Str::random(64);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function episodes()
    {
        return $this->hasMany(VoEpisode::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function getCoverArtUrlResolvedAttribute(): ?string
    {
        return $this->cover_art_url ?: null;
    }

    public function getRssUrlAttribute(): string
    {
        return route('addon.vo.public.rss', ['token' => $this->rss_token]);
    }
}
