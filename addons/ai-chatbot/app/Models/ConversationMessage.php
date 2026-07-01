<?php

declare(strict_types=1);

namespace Addons\AiChatbot\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'conversation_id', 'role', 'content', 'model',
        'input_tokens', 'output_tokens', 'credits_charged',
        'attachments', 'product_switch',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'credits_charged' => 'decimal:4',
        ];
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
