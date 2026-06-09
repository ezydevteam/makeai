<?php

namespace App\Services;

use App\Models\AiTool;
use App\Models\Category;
use App\Models\BlogPost;
use App\Models\Page;
use Illuminate\Support\Facades\Cache;

/**
 * SitemapService — generates XML sitemap for all public routes.
 *
 * Cached for 24 hours. Invalidated on tool/category/page/blog create/update/delete.
 */
class SitemapService
{
    public function generate(): string
    {
        return Cache::remember('sitemap:xml', now()->addHours(24), function () {
            $baseUrl = rtrim(config('app.url'), '/');
            $urls = [];

            // Static pages
            $urls[] = $this->urlEntry($baseUrl, '1.0', 'daily');
            $urls[] = $this->urlEntry("{$baseUrl}/ai-tools", '0.9', 'daily');
            $urls[] = $this->urlEntry("{$baseUrl}/blog", '0.8', 'weekly');
            $urls[] = $this->urlEntry("{$baseUrl}/pricing", '0.7', 'weekly');
            $urls[] = $this->urlEntry("{$baseUrl}/contact", '0.5', 'monthly');

            // Tool pages
            $tools = AiTool::active()->whereHas('category', fn ($q) => $q->where('is_active', true))->get(['slug', 'updated_at']);
            foreach ($tools as $tool) {
                $urls[] = $this->urlEntry(
                    "{$baseUrl}/ai-tools/{$tool->slug}",
                    '0.9',
                    'weekly',
                    $tool->updated_at?->toAtomString()
                );
            }

            // Tool category pages
            $categories = Category::active()->where('type', 'ai_tool')->get(['slug', 'updated_at']);
            foreach ($categories as $cat) {
                $urls[] = $this->urlEntry(
                    "{$baseUrl}/ai-tools/category/{$cat->slug}",
                    '0.8',
                    'weekly',
                    $cat->updated_at?->toAtomString()
                );
            }

            // Blog posts
            if (class_exists(BlogPost::class)) {
                $posts = BlogPost::published()->get(['slug', 'updated_at']);
                foreach ($posts as $post) {
                    $urls[] = $this->urlEntry(
                        "{$baseUrl}/blog/{$post->slug}",
                        '0.7',
                        'monthly',
                        $post->updated_at?->toAtomString()
                    );
                }
            }

            // Custom pages
            if (class_exists(Page::class)) {
                $pages = Page::where('is_active', true)->get(['slug', 'updated_at']);
                foreach ($pages as $page) {
                    $urls[] = $this->urlEntry(
                        "{$baseUrl}/page/{$page->slug}",
                        '0.6',
                        'monthly',
                        $page->updated_at?->toAtomString()
                    );
                }
            }

            return $this->buildXml($urls);
        });
    }

    public function invalidate(): void
    {
        Cache::forget('sitemap:xml');
    }

    private function urlEntry(string $loc, string $priority, string $changefreq, ?string $lastmod = null): string
    {
        $xml = "  <url>\n";
        $xml .= "    <loc>{$loc}</loc>\n";
        if ($lastmod) {
            $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
        }
        $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
        $xml .= "    <priority>{$priority}</priority>\n";
        $xml .= "  </url>";

        return $xml;
    }

    private function buildXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= implode("\n", $urls) . "\n";
        $xml .= '</urlset>';

        return $xml;
    }
}
