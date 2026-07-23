<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Docs\ProductDocsService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Renders the core documentation corpus into one self-contained HTML file.
 *
 * This is the offline manual shipped in the release (documentation/docs.html) and the
 * artifact that satisfies Envato's documentation requirement. It is generated, never
 * hand-maintained: the markdown in resources/docs/core is the single source of truth, and
 * build-release.php regenerates this on every build so it cannot drift from the corpus.
 *
 * Two deliberate exclusions:
 *
 *  - ADDONS. Addon pages live in addons/<slug>/docs and travel in the addon's own package.
 *    Documenting a paid addon inside the core manual would describe features most buyers
 *    have not bought; those pages are on the docs site instead.
 *  - NOTHING ELSE. Extended-license pages ARE included, badged. One file ships to every
 *    buyer, so hiding them would mean maintaining two builds, and a Regular buyer reading
 *    what Extended adds is a feature, not a leak.
 */
class BuildDocsHtml extends Command
{
    protected $signature = 'docs:build-html
        {--output= : Destination path (default: storage/app/docs.html)}
        {--corpus= : Source directory (default: resources/docs/core)}
        {--app-version= : Version printed in the header}';

    protected $description = 'Render the core documentation corpus into a single offline HTML file';

    public function handle(): int
    {
        // An explicit corpus path keeps ProductDocsService from scanning addons/*/docs —
        // which is exactly what this command wants, addons being excluded by design.
        $corpus = $this->option('corpus') ?: resource_path('docs/core');

        if (! is_dir($corpus)) {
            $this->error("Corpus directory does not exist: {$corpus}");

            return self::FAILURE;
        }

        $pages = (new ProductDocsService($corpus))->corpus();

        if ($pages === []) {
            $this->error("No markdown pages found in: {$corpus}");

            return self::FAILURE;
        }

        $output = $this->option('output') ?: storage_path('app/docs.html');

        $dir = dirname($output);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            $this->error("Could not create output directory: {$dir}");

            return self::FAILURE;
        }

        $html = $this->render($this->group($pages), $this->option('app-version') ?: '');

        if (file_put_contents($output, $html) === false) {
            $this->error("Could not write to {$output}");

            return self::FAILURE;
        }

        $this->info(sprintf(
            '✔ Wrote %s (%s KB, %d pages, %d sections).',
            $output,
            number_format(strlen($html) / 1024, 1),
            count($pages),
            count($this->group($pages)),
        ));

        return self::SUCCESS;
    }

    /**
     * Reading order for the manual. A buyer works down this list on their first day:
     * install, license, settings, then the things they configure once, then the rest.
     * Sections not listed here are appended alphabetically rather than dropped, so a new
     * section shows up in the output the day it is written.
     */
    private const SECTION_ORDER = [
        'System', 'Settings', 'Dashboard', 'AI', 'Credits', 'Roles', 'Payments',
        'Content', 'Appearance', 'Communications', 'Mail', 'Marketing', 'Languages',
        'Export Center', 'Addons',
    ];

    /**
     * @param  list<array<string,mixed>>  $pages
     * @return array<string,list<array<string,mixed>>>
     */
    private function group(array $pages): array
    {
        $grouped = [];

        foreach ($pages as $page) {
            $grouped[$page['section'] !== '' ? $page['section'] : 'General'][] = $page;
        }

        uksort($grouped, function (string $a, string $b): int {
            $ai = array_search($a, self::SECTION_ORDER, true);
            $bi = array_search($b, self::SECTION_ORDER, true);

            return match (true) {
                $ai !== false && $bi !== false => $ai <=> $bi,
                $ai !== false => -1,
                $bi !== false => 1,
                default => strcmp($a, $b),
            };
        });

        return $grouped;
    }

    /** @param  array<string,list<array<string,mixed>>>  $sections */
    private function render(array $sections, string $version): string
    {
        $site = ProductDocsService::siteUrl();
        $nav = '';
        $body = '';

        // Every page in the file, indexed by slug. A cross-link's target IS the slug of the
        // article it points at, and in this single self-contained file that slug is an
        // element id — so a link resolves to an in-file anchor only when its target ships here.
        $knownSlugs = [];
        foreach ($sections as $pages) {
            foreach ($pages as $page) {
                $knownSlugs[$page['slug']] = true;
            }
        }

        foreach ($sections as $section => $pages) {
            $sectionId = 'section-' . Str::slug($section);

            $nav .= '<li class="nav-section"><h3 id="nav-' . $sectionId . '">' . $this->e($section) . '</h3><ul>';

            foreach ($pages as $page) {
                $nav .= '<li><a href="#' . $this->e($page['slug']) . '" data-terms="'
                    . $this->e(mb_strtolower($page['title'] . ' ' . implode(' ', $page['keywords'])))
                    . '">' . $this->e($page['title']) . '</a></li>';
            }

            $nav .= '</ul></li>';

            $body .= '<section class="doc-section" id="' . $sectionId . '"><h2 class="section-heading">'
                . $this->e($section) . '</h2>';

            foreach ($pages as $page) {
                $badge = $page['license'] === 'extended'
                    ? '<span class="badge" title="This feature requires the Extended License">Extended License</span>'
                    : '';

                $online = $page['page'] !== ''
                    ? '<a class="online" href="' . $this->e(rtrim($site, '/') . '/' . ltrim($page['page'], '/'))
                        . '" target="_blank" rel="noopener">Read online &rarr;</a>'
                    : '';

                $body .= '<article class="doc" id="' . $this->e($page['slug']) . '">'
                    . '<header class="doc-head"><h2>' . $this->e($page['title']) . '</h2>'
                    . $badge . $online . '</header>'
                    . '<div class="doc-body">' . $this->markdown($page['body'], $knownSlugs) . '</div>'
                    . '<a class="top" href="#top">Back to top</a>'
                    . '</article>';
            }

            $body .= '</section>';
        }

        $subtitle = $version !== '' ? 'Version ' . $this->e($version) : 'Product Documentation';

        return $this->shell($nav, $body, $subtitle, $site);
    }

    /**
     * Markdown bodies start at `##` because the page title comes from front-matter, so
     * headings are demoted one level here to sit correctly under the article's <h2>.
     *
     * @param  array<string,bool>  $knownSlugs
     */
    private function markdown(string $body, array $knownSlugs = []): string
    {
        $html = Str::markdown($body);

        $html = preg_replace_callback(
            '/<(\/?)h([2-5])>/',
            fn (array $m): string => '<' . $m[1] . 'h' . min(6, (int) $m[2] + 1) . '>',
            $html
        ) ?? $html;

        return $this->resolveCrossLinks($html, $knownSlugs);
    }

    /**
     * Turn in-corpus cross-links into in-file anchors.
     *
     * Authors link between pages by bare slug — `[Credit modes](credit-modes)` — which the
     * docs site rewrites to `credit-modes.html` but CommonMark renders here as a raw
     * `href="credit-modes"`. In one self-contained file that would navigate to a sibling file
     * that does not exist, so the link is broken offline unless it is turned into `#credit-modes`,
     * the id this file already gives that article.
     *
     * A slug with no page in this file (a link to something that only lives on the docs site,
     * or a page not yet written) has no offline target at all. Following articleUrl()'s rule
     * that a citation which 404s is worse than one that is not a link, its anchor is unwrapped
     * to plain text rather than shipped as a dead link. The pattern only matches bare slugs
     * (`[a-z0-9-]+`), so absolute URLs, `#top`, and `.html` links are left untouched.
     *
     * @param  array<string,bool>  $knownSlugs
     */
    private function resolveCrossLinks(string $html, array $knownSlugs): string
    {
        return preg_replace_callback(
            '/<a href="([a-z0-9-]+)"([^>]*)>(.*?)<\/a>/is',
            function (array $m) use ($knownSlugs): string {
                [$slug, $attrs, $text] = [$m[1], $m[2], $m[3]];

                return isset($knownSlugs[$slug])
                    ? '<a href="#' . $slug . '"' . $attrs . '>' . $text . '</a>'
                    : $text;
            },
            $html
        ) ?? $html;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** The page shell: all CSS and JS inline, so the file works from a USB stick. */
    private function shell(string $nav, string $body, string $subtitle, string $site): string
    {
        $css = $this->css();
        $siteEscaped = $this->e($site);
        $year = date('Y');

        return <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>MakeAI — Documentation</title>
        <style>{$css}</style>
        </head>
        <body id="top">
        <header class="masthead">
          <div class="masthead-inner">
            <div>
              <h1>MakeAI Documentation</h1>
              <p class="subtitle">{$subtitle}</p>
            </div>
            <a class="site-link" href="{$siteEscaped}" target="_blank" rel="noopener">
              Online documentation &rarr;
            </a>
          </div>
        </header>

        <div class="notice">
          <strong>This is the offline copy of the core manual.</strong>
          Documentation for addons (AI Chatbot, AI Image Pro, Knowledge Base, FakerAI, AI
          Assistant) is published online only, together with the latest version of these
          pages, at <a href="{$siteEscaped}" target="_blank" rel="noopener">{$siteEscaped}</a>.
        </div>

        <div class="layout">
          <aside class="sidebar">
            <input type="search" id="filter" placeholder="Filter pages…" aria-label="Filter pages">
            <nav><ul id="nav">{$nav}</ul></nav>
          </aside>
          <main class="content">{$body}</main>
        </div>

        <footer class="footer">
          <nav class="footer-menu">
            <a href="{$siteEscaped}" target="_blank" rel="noopener">Documentation</a>
            <a href="https://makeai.ezydev.net" target="_blank" rel="noopener">Support</a>
            <a href="https://codecanyon.net" target="_blank" rel="noopener">Buy more</a>
          </nav>
          <p>&copy; {$year} &middot; Developed by <a href="https://ezydev.net" target="_blank" rel="noopener">EzyDev</a></p>
        </footer>

        <script>
        (function () {
          var filter = document.getElementById('filter');
          var nav = document.getElementById('nav');
          if (!filter || !nav) return;

          var links = Array.prototype.slice.call(nav.querySelectorAll('a[data-terms]'));
          var groups = Array.prototype.slice.call(nav.querySelectorAll('.nav-section'));

          filter.addEventListener('input', function () {
            var q = filter.value.trim().toLowerCase();

            links.forEach(function (link) {
              var hit = q === '' || link.getAttribute('data-terms').indexOf(q) !== -1;
              link.parentNode.hidden = !hit;
            });

            // Hide a section header once every page under it has been filtered out.
            groups.forEach(function (group) {
              var visible = group.querySelectorAll('li:not([hidden])').length;
              group.hidden = visible === 0;
            });
          });

          // Highlight the sidebar entry for the article currently in view.
          var docs = Array.prototype.slice.call(document.querySelectorAll('.doc'));
          var linkFor = {};
          links.forEach(function (link) {
            var id = (link.getAttribute('href') || '').slice(1);
            if (id) linkFor[id] = link;
          });

          function highlight() {
            if (!docs.length) return;
            // The current article is the last one whose top has scrolled past the marker line.
            var marker = 100;
            var currentId = docs[0].id;
            for (var i = 0; i < docs.length; i++) {
              if (docs[i].getBoundingClientRect().top <= marker) currentId = docs[i].id;
              else break;
            }
            links.forEach(function (link) { link.classList.remove('active'); });
            if (linkFor[currentId]) linkFor[currentId].classList.add('active');
          }

          window.addEventListener('scroll', highlight, { passive: true });
          window.addEventListener('hashchange', highlight);
          highlight();
        })();
        </script>
        </body>
        </html>
        HTML;
    }

    private function css(): string
    {
        return <<<'CSS'
        *,*::before,*::after{box-sizing:border-box}
        :root{
          --bg:#ffffff;--fg:#1f2430;--muted:#5b6478;--line:#e3e7ee;--accent:#4f46e5;
          --code-bg:#f5f7fa;--notice-bg:#eef2ff;--badge-bg:#fef3c7;--badge-fg:#92400e;
        }
        @media (prefers-color-scheme:dark){
          :root{
            --bg:#14171f;--fg:#e6e9ef;--muted:#98a1b3;--line:#272c38;--accent:#8b87f5;
            --code-bg:#1c212b;--notice-bg:#1d2130;--badge-bg:#3d2f10;--badge-fg:#fbbf24;
          }
        }
        body{margin:0;background:var(--bg);color:var(--fg);
          font:16px/1.65 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
        a{color:var(--accent)}
        .masthead{border-bottom:1px solid var(--line);padding:22px 28px}
        .masthead-inner{max-width:1180px;margin:0 auto;display:flex;gap:20px;
          align-items:center;justify-content:space-between;flex-wrap:wrap}
        .masthead h1{margin:0;font-size:21px;letter-spacing:-.01em}
        .subtitle{margin:2px 0 0;color:var(--muted);font-size:13px}
        .site-link{font-size:14px;font-weight:600;text-decoration:none;border:1px solid var(--line);
          padding:8px 14px;border-radius:8px}
        .site-link:hover{border-color:var(--accent)}
        .notice{max-width:1180px;margin:20px auto 0;padding:14px 18px;background:var(--notice-bg);
          border:1px solid var(--line);border-radius:10px;font-size:14px;line-height:1.6}
        .layout{max-width:1180px;margin:0 auto;display:flex;gap:36px;padding:24px 28px 60px;align-items:flex-start}
        .sidebar{position:sticky;top:20px;flex:0 0 250px;max-height:calc(100vh - 40px);overflow-y:auto}
        #filter{width:100%;padding:9px 11px;border:1px solid var(--line);border-radius:8px;
          background:var(--bg);color:var(--fg);font-size:14px;margin-bottom:14px}
        .sidebar ul{list-style:none;margin:0;padding:0}
        .nav-section h3{margin:18px 0 6px;font-size:11px;text-transform:uppercase;
          letter-spacing:.09em;color:var(--muted)}
        .nav-section a{display:block;padding:5px 9px;font-size:14px;text-decoration:none;
          color:var(--fg);border-radius:6px;border-left:2px solid transparent}
        .nav-section a:hover{background:var(--code-bg);border-left-color:var(--accent)}
        .nav-section a.active{background:var(--code-bg);border-left-color:var(--accent);
          color:var(--accent);font-weight:600}
        .content{flex:1;min-width:0}
        .section-heading{margin:44px 0 0;font-size:12px;text-transform:uppercase;
          letter-spacing:.1em;color:var(--muted);font-weight:700}
        .doc-section:first-child .section-heading{margin-top:0}
        .doc{border-top:1px solid var(--line);padding:26px 0 8px;scroll-margin-top:20px}
        .doc-head{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:6px}
        .doc-head h2{margin:0;font-size:25px;letter-spacing:-.015em}
        .badge{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;
          background:var(--badge-bg);color:var(--badge-fg);padding:3px 8px;border-radius:999px}
        .online{margin-left:auto;font-size:13px;text-decoration:none;white-space:nowrap}
        .online:hover{text-decoration:underline}
        .doc-body h3{margin:26px 0 8px;font-size:18px}
        .doc-body h4{margin:20px 0 6px;font-size:15px}
        .doc-body h5,.doc-body h6{margin:16px 0 6px;font-size:14px}
        .doc-body p,.doc-body li{font-size:15px}
        .doc-body ul,.doc-body ol{padding-left:22px}
        .doc-body li{margin:4px 0}
        .doc-body code{background:var(--code-bg);padding:2px 5px;border-radius:4px;
          font-size:13px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
        .doc-body pre{background:var(--code-bg);padding:14px 16px;border-radius:8px;
          overflow-x:auto;border:1px solid var(--line)}
        .doc-body pre code{background:none;padding:0}
        .doc-body blockquote{margin:16px 0;padding:2px 16px;border-left:3px solid var(--accent);
          color:var(--muted)}
        .doc-body table{border-collapse:collapse;width:100%;margin:16px 0;font-size:14px;display:block;overflow-x:auto}
        .doc-body th,.doc-body td{border:1px solid var(--line);padding:8px 11px;text-align:left}
        .doc-body th{background:var(--code-bg)}
        .doc-body img{max-width:100%;height:auto}
        .top{display:inline-block;margin-top:10px;font-size:12px;color:var(--muted);text-decoration:none}
        .top:hover{color:var(--accent)}
        .footer{border-top:1px solid var(--line);padding:22px 28px;text-align:center;
          color:var(--muted);font-size:13px}
        .footer-menu{display:flex;gap:18px;justify-content:center;flex-wrap:wrap;margin-bottom:10px}
        .footer-menu a{color:var(--muted);text-decoration:none;font-weight:600}
        .footer-menu a:hover{color:var(--accent)}
        @media (max-width:860px){
          .layout{flex-direction:column;gap:0;padding:20px}
          .sidebar{position:static;flex:1 1 auto;width:100%;max-height:none;
            border-bottom:1px solid var(--line);padding-bottom:18px;margin-bottom:10px}
        }
        @media print{
          .sidebar,.site-link,.top,.notice{display:none}
          .layout{display:block;max-width:none;padding:0}
          .doc{page-break-inside:avoid}
          a{color:inherit;text-decoration:none}
        }
        CSS;
    }
}
