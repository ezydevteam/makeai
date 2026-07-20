<?php

declare(strict_types=1);

namespace Addons\AiAssistant\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantCsat extends Model
{
    protected $table = 'assistant_csat';

    protected $fillable = [
        'user_id', 'session_id', 'scope', 'score', 'context_page',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    /**
     * The 5-point scale the chat card offers, highest first (as shown in the UI).
     * Maps a label to its stored score.
     */
    public const SCALE = [
        'Very Good' => 5,
        'Good' => 4,
        'Average' => 3,
        'Low' => 2,
        'Bad' => 1,
    ];
}
