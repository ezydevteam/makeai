<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'placement',
        'content',
        'image_path',
        'link_url',
        'is_active',
        'starts_at',
        'ends_at',
        'views',
        'clicks',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'views' => 'integer',
        'clicks' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function getCtrAttribute()
    {
        if ($this->views === 0) {
            return 0;
        }

        return round(($this->clicks / $this->views) * 100, 2);
    }
}
