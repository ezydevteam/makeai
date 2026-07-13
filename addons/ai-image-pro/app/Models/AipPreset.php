<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AipPreset extends Model
{
    protected $fillable = [
        'name', 'slug', 'prompt_suffix', 'negative_prompt', 'thumb_path', 'sort', 'is_active',
    ];

    protected $appends = ['thumb_url'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort');
    }

    public function getThumbUrlAttribute(): ?string
    {
        return $this->thumb_path ? Storage::disk('public')->url($this->thumb_path) : null;
    }
}
