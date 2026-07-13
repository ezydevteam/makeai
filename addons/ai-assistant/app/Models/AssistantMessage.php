<?php

declare(strict_types=1);

namespace Addons\AiAssistant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'conversation_id', 'role', 'content', 'content_hash',
        'model', 'input_tokens', 'output_tokens', 'credits_charged', 'sources',
    ];

    protected function casts(): array
    {
        return [
            'sources' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'credits_charged' => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'conversation_id');
    }
}
