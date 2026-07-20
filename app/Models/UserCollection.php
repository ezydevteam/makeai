<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class UserCollection extends Model
{
    protected $table = 'user_collections';

    protected $fillable = [
        'user_id', 'name', 'description', 'icon', 'color',
        'is_featured', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (UserCollection $collection) {
            if (empty($collection->ulid)) {
                $collection->ulid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tools(): HasMany
    {
        return $this->hasMany(UserCollectionTool::class, 'collection_id');
    }
}
