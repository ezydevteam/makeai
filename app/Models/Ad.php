<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'type',
        'zone',
        'custom_html',
        'adsense_client',
        'adsense_slot',
        'adsense_format',
        'image_url',
        'link_url',
        'link_target',
        'show_to',
        'is_active',
        'start_at',
        'end_at',
        'impressions',
        'clicks',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', now());
            });
    }

    public function getCtrAttribute()
    {
        if ($this->impressions === 0) {
            return 0;
        }

        return round(($this->clicks / $this->impressions) * 100, 2);
    }

    public function getDisplayTitleAttribute(): string
    {
        return $this->title;
    }
}
