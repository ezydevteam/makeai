<?php

declare(strict_types=1);

namespace Addons\AiChatbot\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Conversation extends Model
{
    // 'session_id' is what identifies a guest's own conversations (user_id is null for
    // them). Leaving it out of $fillable made create() silently drop the value the
    // controller passes, so every guest chat saved with a null session and was then
    // invisible to the very query that looks it up again — guests lost their history the
    // moment the response was sent.
    protected $fillable = [
        'ulid', 'user_id', 'session_id', 'project_id', 'mode_slug',
        'title', 'model', 'total_tokens', 'total_credits',
        'message_count', 'last_message_at', 'is_pinned', 'share_token',
        'parent_conversation_id', 'branch_point_message_id',
    ];

    protected function casts(): array
    {
        return [
            'total_tokens' => 'integer',
            'total_credits' => 'decimal:4',
            'message_count' => 'integer',
            'last_message_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation) {
            if (empty($conversation->ulid)) {
                $conversation->ulid = (string) Str::ulid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(ChatProject::class, 'project_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->oldest();
    }

    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ConversationTag::class, 'conversation_tag');
    }

    public static function findByUlid(string $ulid): ?static
    {
        return static::where('ulid', $ulid)->first();
    }
}
