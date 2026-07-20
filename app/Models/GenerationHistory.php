<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GenerationHistory extends Model
{
    protected $table = 'generation_history';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'tool_slug', 'document_id',
        'prompt_system', 'prompt_user', 'field_values',
        'model', 'provider', 'temperature', 'max_tokens',
        'output_preview', 'tokens_input', 'tokens_output',
        'is_favorited', 'label', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'field_values' => 'array',
            'is_favorited' => 'boolean',
            'temperature' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (GenerationHistory $history) {
            if (empty($history->ulid)) {
                $history->ulid = (string) Str::ulid();
            }
            if (empty($history->created_at)) {
                $history->created_at = now();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
