<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BannedIp extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ip_address', 'reason', 'category', 'banned_at', 'expires_at', 'banned_by',
    ];

    protected function casts(): array
    {
        return [
            'banned_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'banned_by');
    }

    public function scopeActive($query): void
    {
        $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Limit to bans that apply to a given endpoint category. A global ('all')
     * ban always matches; a scoped ban matches only its own category. Passing
     * null (or 'all') matches only global bans.
     */
    public function scopeForEndpoint($query, ?string $category): void
    {
        $query->where(function ($q) use ($category) {
            // 'all' blocks every throttled action; a 'site' (entire-site) ban
            // implies it too, so it also blocks throttled endpoints.
            $q->whereIn('category', ['all', 'site']);

            if ($category !== null && ! in_array($category, ['all', 'site'], true)) {
                $q->orWhere('category', $category);
            }
        });
    }
}
