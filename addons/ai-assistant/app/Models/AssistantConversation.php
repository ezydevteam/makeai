<?php

declare(strict_types=1);

namespace Addons\AiAssistant\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistantConversation extends Model
{
    protected $fillable = [
        'ulid', 'user_id', 'session_id', 'scope', 'ip_hash',
        'title', 'model', 'context_page',
        'input_tokens', 'output_tokens', 'total_credits',
        'message_count', 'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'total_credits' => 'decimal:4',
            'message_count' => 'integer',
            'last_message_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
