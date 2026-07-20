<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VoMusicTrack extends Model
{
    protected $table = 'vo_music_library';

    protected $fillable = [
        'ulid',
        'user_id',
        'name',
        'file_path',
        'file_url',
        'file_size_bytes',
        'duration_seconds',
        'is_shared',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'duration_seconds' => 'integer',
        'is_shared' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $track) {
            if (empty($track->ulid)) {
                $track->ulid = (string) Str::ulid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }
}
