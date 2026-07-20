<?php

declare(strict_types=1);

namespace Addons\AiImageEditor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class IeSession extends Model
{
    protected $fillable = [
        'ulid', 'user_id', 'source_type', 'source_image_id', 'source_path',
        'source_url', 'current_path', 'current_url', 'width', 'height',
        'format', 'last_operation',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
    ];

    protected $appends = ['dimensions_label'];

    protected static function booted(): void
    {
        static::creating(function (self $session) {
            if (empty($session->ulid)) {
                $session->ulid = (string) Str::ulid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function edits(): HasMany
    {
        return $this->hasMany(IeEdit::class, 'ie_session_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function getDimensionsLabelAttribute(): string
    {
        if (! $this->width || ! $this->height) {
            return '';
        }

        return "{$this->width} × {$this->height}px";
    }

    public function currentEdit(): ?IeEdit
    {
        return $this->edits()->where('is_current', true)->latest()->first();
    }

    public function nextVersionNumber(): int
    {
        return ((int) $this->edits()->max('version_number') ?: 0) + 1;
    }
}
