<?php

declare(strict_types=1);

namespace Addons\AiChatbot\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChatMessageFeedback extends Model
{
    protected $table = 'chat_message_feedback';

    protected $fillable = [
        'ulid',
        'user_id',
        'conversation_id',
        'message_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $feedback) {
            if (empty($feedback->ulid)) {
                $feedback->ulid = (string) Str::ulid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'message_id');
    }
}
