<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ToolEmbed extends Model
{
    protected $table = 'tool_embeds';

    protected $fillable = [
        'user_id', 'tool_slug', 'token', 'label',
        'allowed_origins', 'password_hash', 'theme',
        'primary_color', 'show_branding', 'usage_count',
        'last_used_at', 'is_active',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'allowed_origins' => 'array',
            'show_branding' => 'boolean',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ToolEmbed $embed) {
            if (empty($embed->ulid)) {
                $embed->ulid = (string) Str::ulid();
            }
            if (empty($embed->token)) {
                $embed->token = Str::random(64);
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
}
