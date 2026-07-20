<?php

namespace Addons\AiAssistant\Models;

use Illuminate\Database\Eloquent\Model;

class AiAssistantFeedback extends Model
{
    protected $table = 'ai_assistant_feedback';

    protected $fillable = [
        'message_id', 'user_id', 'session_id', 'context_page',
        'message_hash', 'rating', 'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function message()
    {
        return $this->belongsTo(AssistantMessage::class, 'message_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
