<?php

namespace App\Http\Controllers;

use App\Models\AiTool;
use App\Models\Category;
use App\Models\Document;
use App\Models\Language;
use App\Models\Page;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\ContentShortcodes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Number;
use Inertia\Inertia;

class PageController extends Controller
{
    /**
     * Display the specified dynamic page.
     */
    public function show(string $slug)
    {
        $page = Page::query()->with('parent')->where('slug', $slug)->published()->firstOrFail();
        $isPasswordProtected = filled($page->getAttribute('password'));
        $isUnlocked = ! $isPasswordProtected || session()->has("page_unlocked_{$page->id}");

        if (! $isUnlocked) {
            return Inertia::render('PagePassword', [
                'page' => $page->only(['id', 'title', 'slug', 'meta_title', 'meta_description']),
            ]);
        }

        $canonical = route('page.show', $page->slug);
        $rawOgImage = $page->og_image ?: $page->featured_image;
        $ogImage = $rawOgImage ? url(media_url($rawOgImage)) : null;
        $description = $page->meta_description ?: $page->excerpt;
        $title = $page->meta_title ?: $page->title;
        $siteName = settings('site_name', 'MakeAI');
        // Complete document <title>, composed server-side so the crawler tag and the
        // browser tab match exactly — document_title() applies the admin's separator,
        // the same one app.ts appends client-side. `$title` stays site-free for
        // OG/Twitter reuse below.
        $documentTitle = document_title($title);

        // Mirror the visible breadcrumb (Page.vue): Home › [Parent] › Page. The
        // parent hop is only present when a parent page is set, so build the
        // list dynamically and renumber positions to keep the schema valid.
        $breadcrumbItems = [
            ['name' => $siteName, 'item' => route('home')],
        ];
        if ($page->parent) {
            $breadcrumbItems[] = ['name' => $page->parent->title, 'item' => route('page.show', $page->parent->slug)];
        }
        $breadcrumbItems[] = ['name' => $page->title, 'item' => $canonical];

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $page->title,
            'description' => $description,
            'url' => $canonical,
            'breadcrumb' => [
                '@type' => 'BreadcrumbList',
                'itemListElement' => collect($breadcrumbItems)->map(fn (array $item, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['item'],
                ])->all(),
            ],
        ];

        return Inertia::render('Page', [
            'page' => $page,
            // Content split around any [faqs]-style shortcode, with the data each one
            // needs resolved server-side. Page.vue mounts real components between the
            // HTML chunks; a page without shortcodes yields a single html block.
            'contentBlocks' => ContentShortcodes::blocks($page->content),
            'seo' => [
                'title' => $documentTitle,
                'description' => $description,
                'keywords' => $page->meta_keywords,
                'canonical' => $canonical,
                'robots' => $isPasswordProtected ? 'noindex,nofollow' : 'index,follow',
                // Nested contract consumed by the server-rendered head in app.blade.php
                // (crawler-facing). og/twitter + schemas[] are the standard shape.
                'og' => [
                    'title' => $title,
                    'description' => $description,
                    'image' => $ogImage,
                    'type' => 'website',
                    'url' => $canonical,
                ],
                'twitter' => [
                    'card' => $ogImage ? 'summary_large_image' : 'summary',
                    'title' => $title,
                    'description' => $description,
                    'image' => $ogImage,
                ],
                'schemas' => [$schema],
                // Flat keys kept for the client-side <Head> in Page.vue (SPA navigation).
                'og_image' => $ogImage,
                'schema' => $schema,
            ],
            // Extras for the designed About layout (About.vue). Null on every other page,
            // so no other page pays for the counts.
            'about' => $page->slug === 'about' ? $this->aboutData() : null,
            'contactChannels' => $page->slug === 'contact' ? $this->contactChannels() : null,
            'contactSettings' => $page->slug === 'contact' ? [
                'enabled' => (bool) settings('contact_enabled', true),
                'subject_mode' => settings('contact_subject_mode', 'text'),
                'subject_options' => collect(explode("\n", (string) settings('contact_subject_options', '')))
                    ->map(fn ($subject) => trim($subject))
                    ->filter()
                    ->values()
                    ->all(),
                'success_message' => settings('contact_success_message', 'Your message has been sent successfully. We will get back to you soon!'),
            ] : null,
        ]);
    }

    /**
     * The numbers, quotes and calls to action the About page is built around.
     *
     * Every figure is counted from this install's own tables. A count that comes back zero
     * is dropped rather than padded, so a site on day one shows three honest stats instead
     * of claiming a milestone it has not reached — and a demo, with its seeded users and
     * documents, fills all four slots on its own.
     *
     * @return array<string, mixed>
     */
    private function aboutData(): array
    {
        return [
            'stats' => $this->aboutStats(),
            'testimonials' => $this->aboutTestimonials(),
            'cta' => $this->aboutCta(),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function aboutStats(): array
    {
        // Ordered by how much each one says about the platform, then truncated — the four
        // that survive are the four most meaningful ones this install can actually prove.
        $candidates = [
            ['icon' => 'ti ti-wand', 'label' => translate('AI tools'), 'count' => fn (): int => AiTool::active()->count()],
            ['icon' => 'ti ti-sparkles', 'label' => translate('Generations run'), 'count' => fn (): int => (int) AiTool::sum('usage_count')],
            ['icon' => 'ti ti-users', 'label' => translate('Creators on board'), 'count' => fn (): int => User::count()],
            ['icon' => 'ti ti-file-text', 'label' => translate('Documents saved'), 'count' => fn (): int => Document::count()],
            ['icon' => 'ti ti-category', 'label' => translate('Tool categories'), 'count' => fn (): int => Category::aiTools()->active()->count()],
            ['icon' => 'ti ti-language', 'label' => translate('Languages'), 'count' => fn (): int => Language::where('is_active', true)->count()],
        ];

        $stats = collect($candidates)
            ->map(fn (array $stat): array => [
                'icon' => $stat['icon'],
                'label' => $stat['label'],
                'total' => ($stat['count'])(),
            ])
            // Single digits are dropped, not just zeros: "4 documents saved" standing next to
            // "1.1M generations" reads worse than three stats would.
            ->filter(fn (array $stat): bool => $stat['total'] >= 10)
            ->take(4)
            ->map(fn (array $stat): array => [
                'icon' => $stat['icon'],
                'label' => $stat['label'],
                // "1.2K" rather than "1,240": the strip is scanned, not read, and the
                // counter animation in About.vue counts up to whatever prefix this leaves.
                'value' => Number::abbreviate($stat['total'], maxPrecision: 1),
            ])
            ->values();

        // One lonely figure is not a strip. Below two, About.vue skips the band entirely.
        return $stats->count() >= 2 ? $stats->all() : [];
    }

    /**
     * Featured testimonials, falling back to any active one so the band is not empty on an
     * install that never marked a favourite. Returns [] when there are none at all, and the
     * page simply skips the section.
     *
     * @return array<int, array<string, mixed>>
     */
    private function aboutTestimonials(): array
    {
        if (! Schema::hasTable('testimonials')) {
            return [];
        }

        $columns = ['name', 'role', 'company', 'avatar', 'content', 'rating'];

        $testimonials = Testimonial::active()->featured()->ordered()->limit(3)->get($columns);

        if ($testimonials->count() < 3) {
            $testimonials = Testimonial::active()->ordered()->limit(3)->get($columns);
        }

        return $testimonials
            ->map(fn (Testimonial $testimonial): array => [
                'name' => $testimonial->name,
                'role' => trim(implode(', ', array_filter([$testimonial->role, $testimonial->company]))),
                'avatar' => $testimonial->avatar ? media_url($testimonial->avatar) : null,
                'content' => $testimonial->content,
                'rating' => (int) $testimonial->rating,
            ])
            ->all();
    }

    /**
     * The two buttons in the hero and the closing band. Both point at routes that exist for
     * the current visitor: a signed-in reader is offered their dashboard rather than a
     * sign-up form, and sign-up is skipped entirely where registration is closed.
     *
     * @return array<string, array<string, string>|null>
     */
    private function aboutCta(): array
    {
        $primary = match (true) {
            auth()->check() => ['label' => translate('Open your dashboard'), 'href' => route('user.dashboard')],
            (bool) settings('registration_enabled', true) => ['label' => translate('Create a free account'), 'href' => route('register')],
            default => null,
        };

        return [
            'primary' => $primary,
            'secondary' => ['label' => translate('Explore the AI tools'), 'href' => route('ai.tools.index')],
        ];
    }

    /**
     * Ways to reach us shown beside the contact form. Every entry is resolved from real
     * configuration or a route that exists — nothing is invented, so an install with no
     * support address simply shows fewer cards rather than a dead link.
     *
     * @return array<int, array<string, mixed>>
     */
    private function contactChannels(): array
    {
        $channels = [];

        if ($email = trim((string) settings('site_support_email', ''))) {
            $channels[] = [
                'icon' => 'ti ti-mail',
                'label' => translate('Email us'),
                'value' => $email,
                'href' => 'mailto:'.$email,
                'external' => false,
            ];
        }

        if ($url = trim((string) settings('site_support_url', ''))) {
            $channels[] = [
                'icon' => 'ti ti-lifebuoy',
                'label' => translate('Help center'),
                'value' => translate('Browse guides and answers'),
                'href' => $url,
                'external' => true,
            ];
        }

        if (Page::query()->where('slug', 'faq')->published()->exists()) {
            $channels[] = [
                'icon' => 'ti ti-help-circle',
                'label' => translate('FAQ'),
                'value' => translate('Find quick answers'),
                'href' => route('page.show', 'faq'),
                'external' => false,
            ];
        }

        // Tickets live behind auth, so only offer them to someone already signed in.
        if (auth()->check() && settings('tickets_enabled', true)) {
            $channels[] = [
                'icon' => 'ti ti-ticket',
                'label' => translate('Support tickets'),
                'value' => translate('Track an existing request'),
                'href' => route('user.dashboard.support.index'),
                'external' => false,
            ];
        }

        return $channels;
    }

    /**
     * Unlock a password-protected dynamic page.
     */
    public function unlock(Request $request, string $slug)
    {
        $page = Page::query()->where('slug', $slug)->published()->firstOrFail();

        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $password = (string) $page->getAttribute('password');
        $validPassword = Hash::check($request->string('password')->toString(), $password)
            || hash_equals($password, $request->string('password')->toString());

        if (! $validPassword) {
            return back()->withErrors(['password' => translate('The password is incorrect.')]);
        }

        $request->session()->put("page_unlocked_{$page->id}", true);

        return redirect()->route('page.show', $page->slug);
    }
}
