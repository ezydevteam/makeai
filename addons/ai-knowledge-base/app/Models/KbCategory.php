<?php

namespace Addons\AiKnowledgeBase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KbCategory extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'icon',
        'sort_order',
        'is_active',
        'meta_title',
        'meta_desc',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (KbCategory $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::deleting(function (KbCategory $category) {
            $category->articles()->update(['kb_category_id' => null]);
        });
    }

    public function articles()
    {
        return $this->hasMany(KbArticle::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function getPublishedArticlesCountAttribute()
    {
        return $this->articles()->published()->count();
    }
}
