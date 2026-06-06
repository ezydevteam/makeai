<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotProduct extends Model
{
    protected $table = 'chatbot_products';

    protected $fillable = [
        'slug', 'name', 'icon', 'color_hex',
        'system_prompt', 'preferred_models', 'default_model',
        'starter_prompts', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'preferred_models' => 'array',
            'starter_prompts' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
