<?php

namespace Addons\AiAssistant\Models;

use Illuminate\Database\Eloquent\Model;

class AiAssistantRule extends Model
{
    protected $table = 'ai_assistant_rules';

    protected $fillable = [
        'trigger', 'response', 'match_type', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
