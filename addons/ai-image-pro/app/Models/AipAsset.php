<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AipAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ulid', 'user_id', 'guest_ip', 'folder_id', 'job_id', 'parent_id',
        'source', 'operation', 'disk', 'path', 'thumb_path', 'mime',
        'width', 'height', 'bytes', 'prompt', 'negative_prompt',
        'model', 'provider', 'seed', 'params', 'is_favorite', 'expires_at',
    ];

    protected $appends = ['url', 'thumb_url'];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'is_favorite' => 'boolean',
            'width' => 'integer',
            'height' => 'integer',
            'bytes' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $asset) {
            $asset->ulid ??= (string) Str::ulid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(AipFolder::class, 'folder_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(AipJob::class, 'job_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk ?: 'public')->url($this->path);
    }

    public function getThumbUrlAttribute(): string
    {
        $disk = $this->disk ?: 'public';

        return $this->thumb_path
            ? Storage::disk($disk)->url($this->thumb_path)
            : Storage::disk($disk)->url($this->path);
    }

    /** Ownership check that works for both logged-in users and guest sessions. */
    public function ownedBy(?User $user, ?string $guestIp = null): bool
    {
        if ($user) {
            return $this->user_id === $user->id;
        }

        return $this->user_id === null
            && $guestIp !== null
            && $this->guest_ip === $guestIp;
    }

    public function scopeOwnedByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    /** Remove the stored files this row points at. Used on delete + retention sweep. */
    public function deleteFiles(): void
    {
        $disk = Storage::disk($this->disk ?: 'public');

        if ($this->path && $disk->exists($this->path)) {
            $disk->delete($this->path);
        }

        if ($this->thumb_path && $disk->exists($this->thumb_path)) {
            $disk->delete($this->thumb_path);
        }
    }
}
