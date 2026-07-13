<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiChat extends Model
{
    protected $fillable = [
        'ulid', 'user_id', 'title', 'model', 'is_pinned',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (AiChat $chat) {
            if (empty($chat->ulid)) {
                $chat->ulid = (string) Str::ulid();
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

    public function messages()
    {
        return $this->hasMany(AiChatMessage::class, 'chat_id')->orderBy('created_at');
    }

    /**
     * Get messages formatted for API request.
     */
    public function getMessagesForApi(): array
    {
        return $this->messages
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();
    }
}
