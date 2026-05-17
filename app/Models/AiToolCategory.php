<?php

namespace App\Models;

use App\Services\AI\ToolCatalogCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * AiToolCategory — organizes AI templates into groups.
 */
class AiToolCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'icon', 'color',
        'is_active', 'requires_pro', 'sort_order', 'tools_count',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'requires_pro' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AiToolCategory $cat) {
            if (empty($cat->slug)) {
                $cat->slug = Str::slug($cat->name);
            }
        });

        static::saved(function (AiToolCategory $category) {
            ToolCatalogCacheService::invalidateForCategory($category);
        });

        static::deleted(function (AiToolCategory $category) {
            ToolCatalogCacheService::invalidateForCategory($category);
        });
    }

    // ─── Relationships ──────────────────────────

    public function templates()
    {
        return $this->hasMany(AiTemplate::class, 'category_id');
    }

    public function activeTemplates()
    {
        return $this->templates()->where('is_active', true);
    }

    // ─── Scopes ─────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Update the cached tools_count.
     */
    public function updateToolsCount(): void
    {
        $this->update(['tools_count' => $this->activeTemplates()->count()]);
    }
}
