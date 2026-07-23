<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
