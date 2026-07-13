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
        $ogImage = $page->og_image ?: $page->featured_image;
        $description = $page->meta_description ?: $page->excerpt;

        return Inertia::render('Page', [
            'page' => $page,
            'seo' => [
                'title' => $page->meta_title ?: $page->title,
                'description' => $description,
                'keywords' => $page->meta_keywords,
                'canonical' => $canonical,
                'robots' => $isPasswordProtected ? 'noindex,nofollow' : 'index,follow',
                'og_image' => $ogImage ? url(media_url($ogImage)) : null,
                'schema' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebPage',
                    'name' => $page->title,
                    'description' => $description,
                    'url' => $canonical,
                    'breadcrumb' => [
                        '@type' => 'BreadcrumbList',
                        'itemListElement' => [
                            ['@type' => 'ListItem', 'position' => 1, 'name' => settings('app_name', 'Application'), 'item' => route('home')],
                            ['@type' => 'ListItem', 'position' => 2, 'name' => $page->title, 'item' => $canonical],
                        ],
                    ],
                ],
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
