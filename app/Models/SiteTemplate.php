<?php

namespace App\Models;

use App\Services\AI\SiteTemplateCacheService;
use Illuminate\Database\Eloquent\Model;

class SiteTemplate extends Model
{
    protected $table = 'site_templates';

    protected $fillable = [
        'slug', 'name', 'tagline', 'description',
        'preview_image', 'icon', 'layout_component',
        'bundled_tool_slugs', 'requires_pro',

        'color_primary', 'color_secondary', 'color_bg',
        'color_surface', 'color_text',
        'font_heading', 'font_body',

        'hero_headline', 'hero_subheadline',
        'hero_cta_text', 'hero_cta_url', 'hero_bg_image',

        'custom_html_head', 'custom_html_body', 'custom_css',

        'meta_title', 'meta_description', 'og_image',

        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'bundled_tool_slugs' => 'array',
            'requires_pro' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::saved(function (SiteTemplate $template) {
            SiteTemplateCacheService::invalidateForTemplate($template);
        });

        static::deleted(function (SiteTemplate $template) {
            SiteTemplateCacheService::invalidateForTemplate($template);
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function bundledTools()
    {
        $slugs = $this->bundled_tool_slugs ?? [];

        if (empty($slugs)) {
            return collect();
        }

        return AiTool::whereIn('slug', $slugs)
            ->where('is_active', true)
            ->orderByRaw('FIELD(slug, '.collect($slugs)->map(fn ($s) => "'{$s}'")->join(',').')')
            ->get();
    }

    public static function availableForNavigation(): array
    {
        return static::active()
            ->when(! isProAvailable(), fn ($q) => $q->where('requires_pro', false))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon', 'requires_pro'])
            ->toArray();
    }
}
