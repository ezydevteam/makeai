<?php

namespace App\Models;

use App\Services\AI\ToolCatalogCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Category — unified category model for AI tools, blog, and general content.
 *
 * Ref: AI_SaaS_Master_Prompt Part 14.2 + Part 18
 */
class Category extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'icon', 'color',
        'type', 'parent_id',
        'is_active', 'is_system', 'requires_pro',
        'sort_order', 'tools_count',
        'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'requires_pro' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::saved(function (Category $category) {
            ToolCatalogCacheService::invalidateForCategory($category);
        });

        static::deleted(function (Category $category) {
            ToolCatalogCacheService::invalidateForCategory($category);
        });
    }

    // ─── Relationships ──────────────────────────

    public function tools()
    {
        return $this->hasMany(AiTool::class, 'category_id');
    }

    public function activeTools()
    {
        return $this->tools()->where('is_active', true);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function blogPosts()
    {
        return $this->hasMany(BlogPost::class, 'category_id');
    }

    // ─── Scopes ─────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeAiTools($query)
    {
        return $query->ofType('ai_tool');
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    // ─── Methods ────────────────────────────────

    public function updateToolsCount(): void
    {
        $this->update(['tools_count' => $this->activeTools()->count()]);
    }

    public function hasTools(): bool
    {
        return $this->tools()->exists();
    }

    public function isSystem(): bool
    {
        return $this->is_system;
    }
}
