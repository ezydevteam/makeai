<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AiOutputRating extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'tool_slug', 'document_id', 'generation_history_id',
        'rating', 'feedback_text', 'model', 'provider', 'created_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (AiOutputRating $rating) {
            if (empty($rating->ulid)) {
                $rating->ulid = (string) Str::ulid();
            }
            if (empty($rating->created_at)) {
                $rating->created_at = now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function generationHistory(): BelongsTo
    {
        return $this->belongsTo(GenerationHistory::class, 'generation_history_id');
    }
}
