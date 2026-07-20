<?php

namespace Addons\SocialScheduler\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SsSocialAccount extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'platform', 'platform_user_id', 'platform_username',
        'platform_name', 'avatar_url', 'access_token', 'refresh_token',
        'token_expires_at', 'scopes', 'page_id', 'page_name',
        'account_type', 'is_active', 'follower_count', 'followers_updated_at',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'followers_updated_at' => 'datetime',
            'scopes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function postPlatforms()
    {
        return $this->hasMany(SsPostPlatform::class, 'ss_social_account_id');
    }

    public function getPlatformLabelAttribute(): string
    {
        return [
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'twitter' => 'X / Twitter',
            'linkedin' => 'LinkedIn',
            'tiktok' => 'TikTok',
            'pinterest' => 'Pinterest',
            'youtube' => 'YouTube',
        ][$this->platform] ?? $this->platform;
    }

    public function getIsTokenExpiredAttribute(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    public function getDecryptedAccessToken(): string
    {
        return Crypt::decryptString($this->getRawOriginal('access_token'));
    }

    public function getDecryptedRefreshToken(): ?string
    {
        if (! $this->getRawOriginal('refresh_token')) {
            return null;
        }

        return Crypt::decryptString($this->getRawOriginal('refresh_token'));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function getAccessTokenAttribute($value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = Crypt::encryptString($value);
    }

    public function getRefreshTokenAttribute($value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setRefreshTokenAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['refresh_token'] = null;
            return;
        }

        $this->attributes['refresh_token'] = Crypt::encryptString($value);
    }

}
