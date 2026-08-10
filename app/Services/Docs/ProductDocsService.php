<?php

declare(strict_types=1);

namespace App\Services\Docs;

use Illuminate\Support\Facades\Cache;

/**
 * Retrieval over the MakeAI product documentation that ships inside the release.
 *
 * This is NOT the buyer's Knowledge Base addon (that is *their* help centre, written for
 * *their* customers). This is the admin manual for MakeAI itself — how to add a provider
 * key, what the credit modes mean, why cron matters — and it is what the admin assistant
 * grounds on.
 *
 * Three properties are load-bearing, and they are why this does not reuse the KB's RAG
 * pipeline:
 *
 *  - FREE. Scoring is lexical (BM25F), computed in PHP. KbSearchService embeds the query
 *    on every call, which costs an API call per question and needs an embedding-capable
 *    provider key. A built-in feature with no settings cannot depend on either.
 *  - OFFLINE. No table, no migration, no network. It reads markdown off disk, so it works
 *    on shared hosting, behind a firewall, and before the buyer has configured anything.
 *  - VERSIONED. The corpus ships in the zip, so it always describes the version the buyer
 *    actually has — unlike a live docs site, which describes whatever was published last.
 *
 * The corpus is assembled from two places: resources/docs/core (the core manual, always
 * shipped) and addons/<slug>/docs (each addon documents itself, and travels in its own
 * package). Discovery is by filesystem, deliberately: a deactivated-but-installed addon
 * still contributes its pages, which keeps this free of any database dependency and keeps
 * it working before the buyer has configured anything.
 */
final class ProductDocsService
{
    /**
     * Public documentation site, used to turn a citation into a link.
     *
     * Deliberately a code constant rather than an admin setting: the docs belong to the
     * product, not to the buyer's install.
     */
    private const SITE_URL = 'https://makeai-docs.ezydev.net';

    /** Weight applied to a term found in a title, heading, section or keyword list. */
    private const FIELD_BOOST = 3;

    private const K1 = 1.2;

    /**
     * Length normalisation, below the usual 0.75 on purpose.
     *
     * BM25's default rewards short passages hard, which is wrong for a manual: the section
     * that actually answers "how do I add a key" is the one with the steps in it, and it is
     * necessarily longer than the two-line aside about deleting one. At 0.75 the aside won.
     */
    private const B = 0.5;

    /**
     * A chunk must clear this to be considered a match at all, AND must score within this
     * fraction of the best hit. The absolute floor rejects a query that merely brushed a
     * common word; the relative floor stops a strong hit from dragging weak ones in behind
     * it. Injecting irrelevant context is worse than injecting none — it invites the model
     * to answer confidently from the wrong page.
     */
    private const MIN_SCORE = 1.0;

    private const RELATIVE_FLOOR = 0.25;

    /**
     * One page should not fill the whole context window on its own — but an admin question
     * is usually answered by several sections of a SINGLE page, so this is deliberately not
     * as tight as it looks. At 2, a question whose answer lived in a page's third-ranked
     * section could never retrieve it, no matter how relevant the page was.
     */
    private const MAX_CHUNKS_PER_PAGE = 3;

    private const CONTEXT_CHAR_BUDGET = 6000;

    /** Chunks larger than this are split again on paragraph boundaries. */
    private const MAX_CHUNK_CHARS = 1200;

    /** @var array<string,mixed>|null Per-request memo, on top of the cross-request cache. */
    private ?array $memo = null;

    /**
     * @param  string|null  $path  Read ONLY this directory, skipping addon discovery. Used by
     *                             tests and by callers that want an isolated corpus.
     */
    public function __construct(private ?string $path = null) {}

    /** The core corpus: the manual for MakeAI itself, always present in a release. */
    public function path(): string
    {
        return $this->path ?? resource_path('docs/core');
    }

    /**
     * Every directory searched: the core corpus, plus a `docs/` folder inside each installed
     * addon and any one directory below it.
     *
     * Addons document themselves and ship their own pages, so the assistant is grounded on
     * exactly what the buyer actually has. Before this, every addon's manual lived in core
     * and was searchable on every install — so the assistant would confidently walk an admin
     * through configuring an addon they had never bought.
     *
     * The extra level exists because core no longer ships the corpus — the assistant's
     * package carries it, filed under `docs/core/` so the product manual stays visibly
     * separate from the two pages that are actually about the addon. One level, not a
     * recursive walk: the shape is a deliberate convention, not an invitation for an addon
     * to nest arbitrarily deep.
     *
     * An explicit constructor path opts out entirely: that caller asked for one directory.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        if ($this->path !== null) {
            return [$this->path];
        }

        $paths = [$this->path()];

        // glob() returns false on an install with no addons/ directory at all — the shipped
        // package creates it empty, but a stripped one must not fatal here.
        foreach (glob(base_path('addons/*/docs'), GLOB_ONLYDIR) ?: [] as $dir) {
            $paths[] = $dir;

            foreach (glob($dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $sub) {
                $paths[] = $sub;
            }
        }

        return $paths;
    }

    /**
     * Is there a corpus to search? False on an install whose docs folder was stripped, in
     * which case the assistant must fall back to ungrounded chat rather than error.
     */
    public function hasCorpus(): bool
    {
        return $this->files() !== [];
    }

    /**
     * Every page in the corpus, license-filtered. Useful for an admin docs index; the
     * assistant itself only needs search().
     *
     * @return list<array{title:string,slug:string,section:string,url:?string}>
     */
    public function pages(): array
    {
        $seen = [];

        foreach ($this->index()['chunks'] as $chunk) {
            if (! $this->licensePermits($chunk['license'])) {
                continue;
            }

            $seen[$chunk['slug']] ??= [
                'title' => $chunk['title'],
                'slug' => $chunk['slug'],
                'section' => $chunk['section'],
                'url' => $this->articleUrl($chunk['page'] ?? null),
            ];
        }

        return array_values($seen);
    }

    /**
     * Every page as its parsed front-matter plus its raw markdown body, in filename order.
     *
     * Deliberately NOT license-filtered, unlike pages(): the only caller is the offline
     * documentation builder, which ships one file to every buyer and marks Extended pages
     * with a badge rather than hiding them. Exposed here so the front-matter format has
     * exactly one parser.
     *
     * @return list<array{title:string,slug:string,section:string,license:string,page:string,keywords:list<string>,body:string}>
     */
    public function corpus(): array
    {
        $pages = [];

        foreach ($this->files() as $file) {
            $raw = @file_get_contents($file);

            if ($raw === false) {
                continue;
            }

            [$meta, $body] = $this->parseFrontMatter($raw);

            $slug = (string) ($meta['slug'] ?? pathinfo($file, PATHINFO_FILENAME));

            $pages[] = [
                'title' => (string) ($meta['title'] ?? ucwords(str_replace('-', ' ', $slug))),
                'slug' => $slug,
                'section' => (string) ($meta['section'] ?? ''),
                'license' => strtolower((string) ($meta['license'] ?? 'regular')),
                'page' => (string) ($meta['page'] ?? ''),
                'keywords' => array_values(array_map('strval', (array) ($meta['keywords'] ?? []))),
                'body' => trim($body),
            ];
        }

        return $pages;
    }

    /** The public documentation site this corpus is published to. */
    public static function siteUrl(): string
    {
        return self::SITE_URL;
    }

    /**
     * Rank the corpus against a question.
     *
     * Returns the same shape as the KB's getRelevantContext() — ['context', 'sources'] —
     * so the assistant's grounding and citation code is identical for both corpora.
     *
     * @return array{context:string,sources:list<array{title:string,slug:string,url:?string}>}
     */
    public function search(string $query, int $topK = 5): array
    {
        $terms = $this->tokenize($query);

        if ($terms === []) {
            return $this->nothing();
        }

        $index = $this->index();

        if ($index['count'] === 0) {
            return $this->nothing();
        }

        $scored = [];

        foreach ($index['chunks'] as $chunk) {
            // Filtered at query time, never baked into the cached index: an install can be
            // upgraded from Regular to Extended without the docs index going stale.
            if (! $this->licensePermits($chunk['license'])) {
                continue;
            }

            $score = $this->score($chunk, $terms, $index);

            if ($score >= self::MIN_SCORE) {
                $scored[] = ['score' => $score, 'chunk' => $chunk];
            }
        }

        if ($scored === []) {
            return $this->nothing();
        }

        usort($scored, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $cutoff = $scored[0]['score'] * self::RELATIVE_FLOOR;

        return $this->render($scored, $cutoff, $topK);
    }

    // ─── scoring ──────────────────────────────────────────────

    /**
     * BM25F: one score per chunk, with title/heading/keyword hits weighted above body hits
     * so "provider key" ranks the page *called* that above a page that merely mentions it.
     *
     * @param  array<string,mixed>  $chunk
     * @param  list<string>  $terms
     * @param  array<string,mixed>  $index
     */
    private function score(array $chunk, array $terms, array $index): float
    {
        $score = 0.0;
        $avgLen = max(1.0, (float) $index['avg_len']);

        foreach (array_count_values($terms) as $term => $_) {
            $term = (string) $term;

            $freq = ($chunk['terms'][$term] ?? 0)
                + self::FIELD_BOOST * ($chunk['boost'][$term] ?? 0);

            if ($freq <= 0) {
                continue;
            }

            $docsWithTerm = $index['df'][$term] ?? 0;

            $idf = log(1 + (($index['count'] - $docsWithTerm + 0.5) / ($docsWithTerm + 0.5)));

            $denominator = $freq + self::K1 * (1 - self::B + self::B * ($chunk['len'] / $avgLen));

            $score += $idf * (($freq * (self::K1 + 1)) / $denominator);
        }

        return $score;
    }

    /**
     * Turn ranked chunks into the prompt context block plus a deduplicated source list,
     * respecting the per-page cap and the character budget.
     *
     * @param  list<array{score:float,chunk:array<string,mixed>}>  $scored
     * @return array{context:string,sources:list<array{title:string,slug:string,url:?string}>}
     */
    private function render(array $scored, float $cutoff, int $topK): array
    {
        $perPage = [];
        $blocks = [];
        $sources = [];
        $budget = self::CONTEXT_CHAR_BUDGET;

        foreach ($scored as $hit) {
            if (count($blocks) >= $topK || $hit['score'] < $cutoff) {
                break;
            }

            $chunk = $hit['chunk'];
            $slug = $chunk['slug'];

            if (($perPage[$slug] ?? 0) >= self::MAX_CHUNKS_PER_PAGE) {
                continue;
            }

            $heading = $chunk['heading'] !== '' ? " › {$chunk['heading']}" : '';
            $block = "Documentation: {$chunk['title']}{$heading}\n{$chunk['text']}";

            if (mb_strlen($block) > $budget) {
                break;
            }

            $budget -= mb_strlen($block);
            $perPage[$slug] = ($perPage[$slug] ?? 0) + 1;
            $blocks[] = $block;

            $sources[$slug] ??= [
                'title' => $chunk['title'],
                'slug' => $slug,
                'url' => $this->articleUrl($chunk['page'] ?? null),
            ];
        }

        if ($blocks === []) {
            return $this->nothing();
        }

        return [
            'context' => implode("\n\n---\n\n", $blocks),
            'sources' => array_values($sources),
        ];
    }

    // ─── index ────────────────────────────────────────────────

    /**
     * The parsed, chunked, term-counted corpus.
     *
     * Cached across requests under a fingerprint of the folder's contents, so editing a
     * page (or shipping an update) invalidates it with no artisan command to run — buyers
     * never index anything. A cache failure degrades to building it inline: a slow answer
     * beats no answer.
     *
     * @return array{chunks:list<array<string,mixed>>,df:array<string,int>,count:int,avg_len:float}
     */
    private function index(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $fingerprint = $this->fingerprint();

        if ($fingerprint === '') {
            return $this->memo = ['chunks' => [], 'df' => [], 'count' => 0, 'avg_len' => 0.0];
        }

        try {
            return $this->memo = Cache::remember(
                "product_docs.index.{$fingerprint}",
                now()->addDay(),
                fn (): array => $this->buildIndex(),
            );
        } catch (\Throwable) {
            return $this->memo = $this->buildIndex();
        }
    }

    /** @return array{chunks:list<array<string,mixed>>,df:array<string,int>,count:int,avg_len:float} */
    private function buildIndex(): array
    {
        $chunks = [];

        foreach ($this->files() as $file) {
            $raw = @file_get_contents($file);

            if ($raw === false) {
                continue;
            }

            [$meta, $body] = $this->parseFrontMatter($raw);

            $slug = (string) ($meta['slug'] ?? pathinfo($file, PATHINFO_FILENAME));
            $title = (string) ($meta['title'] ?? ucwords(str_replace('-', ' ', $slug)));
            $section = (string) ($meta['section'] ?? '');
            $license = strtolower((string) ($meta['license'] ?? 'regular'));
            $keywords = (array) ($meta['keywords'] ?? []);
            $page = (string) ($meta['page'] ?? '');

            foreach ($this->chunk($body) as $piece) {
                $bodyTerms = array_count_values($this->tokenize($piece['text']));

                $boostTerms = array_count_values($this->tokenize(
                    implode(' ', [$title, $section, $piece['heading'], implode(' ', $keywords)])
                ));

                $chunks[] = [
                    'slug' => $slug,
                    'title' => $title,
                    'section' => $section,
                    'license' => $license,
                    'page' => $page,
                    'heading' => $piece['heading'],
                    'text' => $piece['text'],
                    'terms' => $bodyTerms,
                    'boost' => $boostTerms,
                    'len' => (float) array_sum($bodyTerms),
                ];
            }
        }

        $documentFrequency = [];
        $totalLength = 0.0;

        foreach ($chunks as $chunk) {
            $totalLength += $chunk['len'];

            foreach (array_keys($chunk['terms'] + $chunk['boost']) as $term) {
                $documentFrequency[$term] = ($documentFrequency[$term] ?? 0) + 1;
            }
        }

        $count = count($chunks);

        return [
            'chunks' => $chunks,
            'df' => $documentFrequency,
            'count' => $count,
            'avg_len' => $count > 0 ? $totalLength / $count : 0.0,
        ];
    }

    /**
     * A cheap content fingerprint. Empty string when there is no corpus at all, which is
     * how hasCorpus() and index() both detect a stripped docs folder.
     */
    private function fingerprint(): string
    {
        $files = $this->files();

        if ($files === []) {
            return '';
        }

        // Keyed on the full path, not the basename: two addons could ship a page with the
        // same filename, and installing or removing an addon must move the fingerprint.
        $parts = array_map(
            fn (string $file): string => $file . '|' . (@filemtime($file) ?: 0) . '|' . (@filesize($file) ?: 0),
            $files
        );

        return md5(implode("\n", $parts));
    }

    /** @return list<string> */
    private function files(): array
    {
        $files = [];
        $seen = [];

        foreach ($this->paths() as $dir) {
            foreach (glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '*.md') ?: [] as $file) {
                // The same page can legitimately appear twice: core no longer ships the
                // corpus (the assistant's package carries it instead), but an install
                // whose core predates that change still has resources/docs/core while the
                // updated addon brings its own copy. Indexing both double-weights the page
                // in scoring and cites it twice, so first path wins — core before addons.
                $name = strtolower(basename($file));

                if (isset($seen[$name])) {
                    continue;
                }

                $seen[$name] = true;
                $files[] = $file;
            }
        }

        // Sorted so the fingerprint is stable regardless of the order the filesystem
        // enumerated the addon directories in.
        sort($files);

        return array_values($files);
    }

    // ─── parsing ──────────────────────────────────────────────

    /**
     * Minimal YAML front-matter reader: `key: value` and `key: [a, b, c]`.
     *
     * Hand-rolled rather than pulled from a package because this ships to buyers who never
     * run `composer install` — a new dependency is not free here. The corpus format is ours
     * to define, so it is defined as exactly the subset parsed below.
     *
     * @return array{0:array<string,mixed>,1:string}
     */
    private function parseFrontMatter(string $raw): array
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

        if (! preg_match('/^---\r?\n(.*?)\r?\n---\r?\n?(.*)$/s', $raw, $matches)) {
            return [[], $raw];
        }

        $meta = [];

        foreach (preg_split('/\r?\n/', $matches[1]) ?: [] as $line) {
            if (! preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*:\s*(.*)$/', trim($line), $pair)) {
                continue;
            }

            $key = strtolower($pair[1]);
            $value = trim($pair[2]);

            if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
                $meta[$key] = array_values(array_filter(
                    array_map(
                        fn (string $item): string => trim($item, " \t\"'"),
                        explode(',', substr($value, 1, -1))
                    ),
                    fn (string $item): bool => $item !== ''
                ));

                continue;
            }

            $meta[$key] = trim($value, "\"'");
        }

        return [$meta, $matches[2]];
    }

    /**
     * Split a page on its `##`/`###` headings, then split any oversized section on
     * paragraph boundaries. Heading-aligned chunks keep a retrieved excerpt self-contained
     * — the model sees a whole "Rotating a key" section, not a sentence torn out of one.
     *
     * @return list<array{heading:string,text:string}>
     */
    private function chunk(string $body): array
    {
        $sections = [];
        $heading = '';
        $buffer = [];

        $flush = function () use (&$sections, &$heading, &$buffer): void {
            $text = trim(implode("\n", $buffer));

            if ($text !== '') {
                $sections[] = ['heading' => $heading, 'text' => $text];
            }

            $buffer = [];
        };

        foreach (preg_split('/\r?\n/', trim($body)) ?: [] as $line) {
            if (preg_match('/^#{2,6}\s+(.*)$/', $line, $found)) {
                $flush();
                $heading = trim($found[1]);

                continue;
            }

            $buffer[] = $line;
        }

        $flush();

        $chunks = [];

        foreach ($sections as $section) {
            foreach ($this->splitLongText($section['text']) as $text) {
                $chunks[] = ['heading' => $section['heading'], 'text' => $text];
            }
        }

        return $chunks;
    }

    /** @return list<string> */
    private function splitLongText(string $text): array
    {
        if (mb_strlen($text) <= self::MAX_CHUNK_CHARS) {
            return [$text];
        }

        $parts = [];
        $current = '';

        foreach (preg_split('/\n{2,}/', $text) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if ($current !== '' && mb_strlen($current) + mb_strlen($paragraph) > self::MAX_CHUNK_CHARS) {
                $parts[] = $current;
                $current = $paragraph;

                continue;
            }

            $current = $current === '' ? $paragraph : $current . "\n\n" . $paragraph;
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    // ─── terms ────────────────────────────────────────────────

    /**
     * Words worth matching on. Markdown syntax and code fences are stripped first so a
     * page is not ranked on its backticks, and terms are stemmed so "keys" finds "key".
     *
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/```.*?```/s', ' ', $text) ?? $text;
        $text = preg_replace('/[`*_>#\[\]()|-]+/', ' ', $text) ?? $text;

        $words = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $terms = [];

        foreach ($words as $word) {
            if (mb_strlen($word) < 2 || isset(self::STOPWORDS[$word])) {
                continue;
            }

            $terms[] = $this->stem($word);
        }

        return $terms;
    }

    /**
     * Crude, deliberately. Applied identically to the corpus and to the query, so it only
     * has to be *consistent*, not linguistically correct: "removing" stems to "remov", which
     * is not a word, but the query stems to "remov" too and they meet.
     *
     * The gerund rule is the one that earns its keep. Documentation heads its sections with
     * gerunds — "Adding a key", "Installing an addon", "Rotating a key" — while admins ask
     * in the infinitive ("how do I add a key"). Without it, the section that answers the
     * question wins no heading boost at all, and a passing mention elsewhere on the page
     * outranks it. That is not hypothetical: it is exactly what this scorer did before.
     */
    private function stem(string $word): string
    {
        if (mb_strlen($word) > 4 && str_ends_with($word, 'ies')) {
            $word = mb_substr($word, 0, -3) . 'y';
        } elseif (mb_strlen($word) > 3 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss')) {
            $word = mb_substr($word, 0, -1);
        }

        if (mb_strlen($word) > 5 && str_ends_with($word, 'ing')) {
            return mb_substr($word, 0, -3);
        }

        return $word;
    }

    /** Words too common to carry signal; matching on them only adds noise. */
    private const STOPWORDS = [
        'a' => true, 'an' => true, 'and' => true, 'are' => true, 'as' => true, 'at' => true,
        'be' => true, 'been' => true, 'but' => true, 'by' => true, 'can' => true, 'do' => true,
        'does' => true, 'for' => true, 'from' => true, 'get' => true, 'has' => true, 'have' => true,
        'how' => true, 'i' => true, 'if' => true, 'in' => true, 'is' => true, 'it' => true,
        'me' => true, 'my' => true, 'not' => true, 'of' => true, 'on' => true, 'or' => true,
        'that' => true, 'the' => true, 'their' => true, 'them' => true, 'then' => true,
        'there' => true, 'they' => true, 'this' => true, 'to' => true, 'was' => true,
        'we' => true, 'what' => true, 'when' => true, 'where' => true, 'which' => true,
        'why' => true, 'will' => true, 'with' => true, 'you' => true, 'your' => true,
    ];

    // ─── helpers ──────────────────────────────────────────────

    /**
     * Extended-only pages are invisible on a Regular install.
     *
     * Without this the assistant would cheerfully walk an admin through configuring
     * subscriptions on a license that does not include them — a confident, well-cited,
     * completely unusable answer, which is worse than "I don't know".
     */
    private function licensePermits(string $license): bool
    {
        return $license !== 'extended' || is_extended_license();
    }

    /**
     * The published counterpart of a page on the docs site, or null when it has none.
     *
     * Taken from the page's `page:` front-matter (the exact published filename), NEVER
     * derived from the slug. The docs site is a flat set of *.html files whose names do not
     * track our slugs, so slug-derivation would mint a confident 404 for every page that
     * happens not to exist there — and a citation that 404s is worse than a citation that
     * simply isn't a link. A page with no `page:` renders as a plain label instead.
     */
    private function articleUrl(?string $page): ?string
    {
        $page = trim((string) $page);

        if ($page === '' || self::SITE_URL === '') {
            return null;
        }

        return rtrim(self::SITE_URL, '/') . '/' . ltrim($page, '/');
    }

    /** @return array{context:string,sources:list<array{title:string,slug:string,url:?string}>} */
    private function nothing(): array
    {
        return ['context' => '', 'sources' => []];
    }
}
