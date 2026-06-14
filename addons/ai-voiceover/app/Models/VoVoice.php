<?php

declare(strict_types=1);

namespace Addons\AiVoiceover\Models;

use Illuminate\Database\Eloquent\Model;

class VoVoice extends Model
{
    protected $table = 'vo_voices';

    protected $fillable = [
        'provider',
        'provider_voice_id',
        'name',
        'gender',
        'language',
        'accent',
        'preview_url',
        'labels',
        'is_cloned',
        'is_active',
        'synced_at',
    ];

    protected $casts = [
        'labels' => 'array',
        'is_cloned' => 'boolean',
        'is_active' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
