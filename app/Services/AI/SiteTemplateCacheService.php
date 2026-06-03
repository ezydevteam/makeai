<?php

namespace App\Services\AI;

use App\Models\SiteTemplate;
use Illuminate\Support\Facades\Cache;

class SiteTemplateCacheService
{
    private const TEMPLATE_KEY_PREFIX = 'makeai:site_template:';

    private const ACTIVE_TEMPLATES_KEY = 'makeai:site_templates:active';

    private const HOMEPAGE_TEMPLATE_KEY = 'makeai:homepage_template';

    /**
     * Get all active site templates (for admin listing and nav builder).
     */
    public function activeTemplates(): array
    {
        return Cache::rememberForever(self::ACTIVE_TEMPLATES_KEY, function () {
            return SiteTemplate::active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get a single site template by slug.
     */
    public function templateBySlug(string $slug): array
    {
        return Cache::rememberForever(self::TEMPLATE_KEY_PREFIX.$slug, function () use ($slug) {
            return SiteTemplate::where('slug', $slug)->firstOrFail()->toArray();
        });
    }

    /**
     * Get the homepage template (resolved from settings).
     */
    public function homepageTemplate(): ?array
    {
        $slug = settings('homepage_template', 'default');

        if ($slug === 'default') {
            return null;
        }

        return $this->templateBySlug($slug);
    }

    /**
     * Get available templates for the homepage selector (admin).
     */
    public function homepageOptions(): array
    {
        return SiteTemplate::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['slug', 'name', 'requires_pro'])
            ->toArray();
    }

    public static function invalidateForTemplate(SiteTemplate $template): void
    {
        Cache::forget(self::TEMPLATE_KEY_PREFIX.$template->slug);
        Cache::forget(self::TEMPLATE_KEY_PREFIX.$template->getOriginal('slug'));
        Cache::forget(self::ACTIVE_TEMPLATES_KEY);
        Cache::forget(self::HOMEPAGE_TEMPLATE_KEY);
    }

    public static function invalidateHomepage(): void
    {
        Cache::forget(self::HOMEPAGE_TEMPLATE_KEY);
    }

    public static function invalidateAll(): void
    {
        Cache::forget(self::ACTIVE_TEMPLATES_KEY);
        Cache::forget(self::HOMEPAGE_TEMPLATE_KEY);

        SiteTemplate::query()
            ->pluck('slug')
            ->each(fn (string $slug) => Cache::forget(self::TEMPLATE_KEY_PREFIX.$slug));
    }
}
