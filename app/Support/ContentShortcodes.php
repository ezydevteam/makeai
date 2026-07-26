<?php

namespace App\Support;

use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Support\Facades\Schema;

/**
 * Turns CMS page content into a list of render blocks, resolving the shortcodes
 * the frontend knows how to mount as Vue components.
 *
 * The content is rendered client-side (Page.vue v-html), so a shortcode cannot be
 * swapped for HTML here and still be interactive. Instead the content is split
 * around each shortcode and the resolved data travels with the block, letting the
 * page render real components between the HTML chunks.
 *
 * Currently supported:
 *   [faqs]                                    all active FAQs, tabs on top
 *   [faqs category="Billing,3" limit="8"]     scoped + capped
 *   [faqs tabs="sidebar"]                     hidden | top | sidebar
 *   [faqs heading="Common questions"]         optional heading above the list
 */
class ContentShortcodes
{
    /** Shortcode names this parser resolves. Anything else is left as literal text. */
    private const SUPPORTED = ['faqs'];

    private const TAB_STYLES = ['hidden', 'top', 'sidebar'];

    /**
     * Cheap pre-check so callers can skip the split entirely on the common
     * case of a page with no shortcodes at all.
     */
    public static function present(?string $content): bool
    {
        if (blank($content)) {
            return false;
        }

        // Tags are stripped first: an editor can split the name itself across formatting
        // marks (`[fa</span><span>qs …]`), which the plain pattern would miss.
        return (bool) preg_match(
            '/\[\s*(' . implode('|', self::SUPPORTED) . ')\b/i',
            strip_tags($content)
        );
    }

    /**
     * Split content into ordered blocks:
     *   ['type' => 'html', 'html' => '<p>…</p>']
     *   ['type' => 'faqs', 'props' => ['faqs' => [...], 'tabsStyle' => 'top', 'heading' => null]]
     *
     * @return array<int, array<string, mixed>>
     */
    public static function blocks(?string $content): array
    {
        $content = (string) $content;

        if (! self::present($content)) {
            return blank($content) ? [] : [['type' => 'html', 'html' => $content]];
        }

        $content = self::normalize($content);

        // WYSIWYG editors wrap a shortcode typed on its own line in a paragraph.
        // Unwrap those first so the split doesn't leave stray <p></p> fragments.
        $content = (string) preg_replace(
            '#<p[^>]*>\s*(' . self::shortcodeBody() . ')\s*</p>#i',
            '$1',
            $content
        );

        $pieces = preg_split(self::pattern(), $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        $blocks = [];

        // preg_split with DELIM_CAPTURE yields: html, name, attrs, html, name, attrs, …
        for ($i = 0; $i < count($pieces); $i++) {
            if ($i % 3 === 0) {
                $blocks[] = ['type' => 'html', 'html' => $pieces[$i]];

                continue;
            }

            if ($i % 3 === 1) {
                $name = strtolower($pieces[$i]);
                $attrs = self::attributes($pieces[$i + 1] ?? '');

                $resolved = match ($name) {
                    'faqs' => self::faqsBlock($attrs),
                    default => null,
                };

                if ($resolved !== null) {
                    $blocks[] = $resolved;
                }
            }
        }

        // Drop html blocks the split left behind that render nothing — whitespace, or the
        // empty half of a wrapper the shortcode was sitting inside. Self-contained tags
        // count as content, so an image-only or <hr>-only block survives.
        return array_values(array_filter($blocks, function (array $block): bool {
            if ($block['type'] !== 'html') {
                return true;
            }

            return trim(strip_tags($block['html'], '<img><iframe><video><audio><hr>')) !== '';
        }));
    }

    /**
     * Rich-text editors apply formatting marks mid-shortcode, so what reaches the DB is
     * often `[faqs category="</span>Getting Started<span style="…">"]` — the tags land
     * inside the brackets and every attribute parses to garbage. Rewrite any bracketed
     * run that turns out to be a shortcode into its plain-text form first.
     *
     * Only runs on brackets that *are* shortcodes, so ordinary prose like
     * `[see <a href="…">here</a>]` keeps its markup.
     */
    private static function normalize(string $content): string
    {
        return (string) preg_replace_callback('/\[[^\[\]]*\]/s', function (array $match): string {
            $plain = trim(strip_tags($match[0]));
            $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Editors also turn straight quotes into typographic ones.
            $plain = str_replace(['“', '”', '„', '‘', '’'], ['"', '"', '"', "'", "'"], $plain);
            $plain = (string) preg_replace('/\s+/u', ' ', $plain);

            $isShortcode = (bool) preg_match('/^\[\s*(' . implode('|', self::SUPPORTED) . ')\b/i', $plain);

            return $isShortcode ? $plain : $match[0];
        }, $content);
    }

    /**
     * Build the [faqs] block, honouring the category / limit / tabs / heading attributes.
     *
     * @param  array<string, string>  $attrs
     * @return array<string, mixed>
     */
    private static function faqsBlock(array $attrs): array
    {
        $tabsStyle = strtolower(trim($attrs['tabs'] ?? 'top'));
        if (! in_array($tabsStyle, self::TAB_STYLES, true)) {
            $tabsStyle = 'top';
        }

        $heading = trim($attrs['heading'] ?? '');
        $limit = (int) ($attrs['limit'] ?? 0);

        $props = [
            'faqs' => [],
            'tabsStyle' => $tabsStyle,
            'heading' => $heading !== '' ? $heading : null,
        ];

        // The installer renders pages before the CMS tables exist.
        if (! Schema::hasTable('faqs')) {
            return ['type' => 'faqs', 'props' => $props];
        }

        $query = Faq::active()
            ->ordered()
            ->with('category:id,name,sort_order');

        $category = trim($attrs['category'] ?? $attrs['categories'] ?? '');
        if ($category !== '') {
            // A filter that resolves to nothing must return nothing. Falling through to
            // an unfiltered query would publish every FAQ on a page that asked for one
            // category — silently wrong content is worse than a visibly empty block.
            $query->whereIn('category_id', self::resolveCategoryIds($category) ?: [0]);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $props['faqs'] = $query
            ->get(['id', 'question', 'answer', 'category_id', 'sort_order'])
            ->toArray();

        return ['type' => 'faqs', 'props' => $props];
    }

    /**
     * Accept category ids and/or names, comma separated: category="3, Billing".
     *
     * Names match loosely — case, spacing and punctuation are ignored, and a name that
     * matches no category exactly falls back to a substring match, so `category="Billing"`
     * still finds a category actually called "Billing & Plans".
     *
     * @return array<int, int>
     */
    private static function resolveCategoryIds(string $raw): array
    {
        $tokens = collect(explode(',', $raw))
            ->map(fn (string $token): string => trim($token))
            ->filter()
            ->values();

        if ($tokens->isEmpty() || ! Schema::hasTable('faq_categories')) {
            return [];
        }

        $ids = $tokens->filter(fn (string $token): bool => ctype_digit($token))
            ->map(fn (string $token): int => (int) $token);

        $names = $tokens->reject(fn (string $token): bool => ctype_digit($token));

        if ($names->isNotEmpty()) {
            $categories = FaqCategory::query()->get(['id', 'name']);

            foreach ($names as $name) {
                $needle = self::comparable($name);
                if ($needle === '') {
                    continue;
                }

                $exact = $categories->first(
                    fn (FaqCategory $category): bool => self::comparable((string) $category->name) === $needle
                );

                if ($exact !== null) {
                    $ids->push($exact->id);

                    continue;
                }

                $ids = $ids->merge(
                    $categories
                        ->filter(fn (FaqCategory $category): bool => str_contains(self::comparable((string) $category->name), $needle))
                        ->pluck('id')
                );
            }
        }

        return $ids->unique()->values()->all();
    }

    /** Lowercase, alphanumerics only — so "Billing & Plans", "billing-plans" and "Billing Plans" all collapse to one key. */
    private static function comparable(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($value));
    }

    /**
     * Parse key="value", key='value' and bare key=value attribute strings.
     *
     * @return array<string, string>
     */
    private static function attributes(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        preg_match_all('/([a-z_][a-z0-9_-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s\]]+))/i', $raw, $matches, PREG_SET_ORDER);

        $attrs = [];
        foreach ($matches as $match) {
            // Exactly one of the three alternatives matched; the others come back empty.
            $value = '';
            foreach ([2, 3, 4] as $group) {
                if (($match[$group] ?? '') !== '') {
                    $value = $match[$group];
                    break;
                }
            }
            // Entities and typographic quotes were already resolved by normalize().
            $attrs[strtolower($match[1])] = $value;
        }

        return $attrs;
    }

    /** Matching pattern with the name + attributes captured. */
    private static function pattern(): string
    {
        return '/' . self::shortcodeBody() . '/i';
    }

    private static function shortcodeBody(): string
    {
        return '\[(' . implode('|', self::SUPPORTED) . ')((?:\s[^\]]*)?)\]';
    }
}
