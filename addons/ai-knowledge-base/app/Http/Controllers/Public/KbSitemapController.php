<?php

namespace Addons\AiKnowledgeBase\Http\Controllers\Public;

use Addons\AiKnowledgeBase\Models\KbArticle;
use Addons\AiKnowledgeBase\Models\KbCategory;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class KbSitemapController extends Controller
{
    /**
     * XML sitemap of the help center (home + categories + published articles) so
     * search engines can discover and index every article. Deliberately NOT behind
     * the guest-search gate — crawlers are anonymous. Honors the master enable flag.
     */
    public function index(): Response
    {
        abort_unless((bool) addon_setting('ai-knowledge-base', 'enabled', true), 404);

        $slug = trim((string) addon_setting('ai-knowledge-base', 'public_slug', 'help'), '/');
        $base = rtrim(config('app.url'), '/');

        $urls = [];

        // Home
        $urls[] = ['loc' => "{$base}/{$slug}", 'changefreq' => 'daily', 'priority' => '1.0'];

        // Categories (as filtered home views)
        foreach (KbCategory::active()->get(['slug', 'updated_at']) as $category) {
            $urls[] = [
                'loc' => "{$base}/{$slug}?category=" . rawurlencode($category->slug),
                'lastmod' => optional($category->updated_at)->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ];
        }

        // Published articles
        KbArticle::published()
            ->orderByDesc('published_at')
            ->select(['slug', 'updated_at'])
            ->chunk(500, function ($articles) use (&$urls, $base, $slug) {
                foreach ($articles as $article) {
                    $urls[] = [
                        'loc' => "{$base}/{$slug}/article/" . rawurlencode($article->slug),
                        'lastmod' => optional($article->updated_at)->toAtomString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.8',
                    ];
                }
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $u) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>' . "\n";
            if (! empty($u['lastmod'])) {
                $xml .= '    <lastmod>' . $u['lastmod'] . '</lastmod>' . "\n";
            }
            $xml .= '    <changefreq>' . $u['changefreq'] . '</changefreq>' . "\n";
            $xml .= '    <priority>' . $u['priority'] . '</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
