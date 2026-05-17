<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiChatMessage extends Model
{
    protected $fillable = [
        'chat_id', 'role', 'content', 'input_tokens', 'output_tokens', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function chat()
    {
        return $this->belongsTo(AiChat::class, 'chat_id');
    }
}
