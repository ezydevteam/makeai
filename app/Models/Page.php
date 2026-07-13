<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use HasFactory, SoftDeletes;

    protected $hidden = [
        'password',
    ];

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_image',
        'featured_image',
        'show_title',
        'center_title',
        'show_breadcrumbs',
        'show_featured_image',
        'show_sidebar',
        'sidebar_position',
        'container_width',
        'status',
        'published_at',
        'password',
        'parent_id',
        'sort_order',
        'is_system',
        'created_by',
    ];

    protected $casts = [
        'show_title' => 'boolean',
        'center_title' => 'boolean',
        'show_breadcrumbs' => 'boolean',
        'show_featured_image' => 'boolean',
        'show_sidebar' => 'boolean',
        'is_system' => 'boolean',
        'published_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function parent()
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('sort_order');
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoriteable');
    }

    public function scopePublished($query)
    {
        return $query->where(function ($query) {
            $query->where('status', 'published')
                ->orWhere(function ($scheduled) {
                    $scheduled->where('status', 'scheduled')
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', now());
                });
        });
    }

    public function getUrlAttribute()
    {
        return route('page.show', $this->slug);
    }
}
