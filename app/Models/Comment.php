<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'commentable_type', 'commentable_id', 'user_id', 'parent_id',
        'content', 'status', 'guest_name', 'guest_email', 'ip_address', 'likes_count',
    ];

    protected function casts(): array
    {
        return [
            'likes_count' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommentLike::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CommentReport::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function canBeEditedBy(?User $user): bool
    {
        return $user !== null
            && ! $user->is_banned
            && $this->user_id === $user->id
            && $this->created_at?->greaterThanOrEqualTo(now()->subMinutes(15));
    }
}
