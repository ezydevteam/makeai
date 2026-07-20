<?php

declare(strict_types=1);

namespace Addons\AiChatbot\Support;

/**
 * Head/SEO payload for the public chat page.
 *
 * The chat page is publicly indexable (it is not in app.blade.php's noindex path
 * list), but it shipped with a hardcoded title and no description, so it fell back
 * to the generic site defaults. These values come from the addon's SEO settings.
 *
 * Built in one place because two routes render the same chat page — /chat and the
 * homepage, when "homepage_template" is set to ai-chatbot — and their head tags
 * must not drift apart.
 */
final class ChatSeo
{
    /**
     * Inertia props for the chat page head: `meta` (client-side <Head>) and
     * `seo` (server-rendered tags in app.blade.php).
     *
     * Pass the conversation ULID when rendering a specific conversation (/chat/{ulid}).
     * Those URLs are per-user and must never be indexed; the landing page (/chat, and the
     * homepage when it renders chat) stays indexable, which is why this is decided here
     * rather than by adding "chat/*" to app.blade.php's noindex path list — that pattern
     * would also catch the landing page.
     */
    public static function props(?string $ulid = null): array
    {
        $title = self::setting('meta_title') ?? translate('AI Chat');
        $description = self::setting('meta_description');
        $keywords = self::setting('meta_keywords');

        return [
            // Site-free — Chat/Index.vue passes this to <Head :title>, which the global
            // title callback suffixes with the site name (see resources/js/app.ts).
            'meta' => [
                'title' => $title,
                'description' => $description,
            ],
            // Server-rendered so crawlers and social scrapers see the tags without
            // running JS. document_title() applies the admin's title separator, which
            // is what the client callback composes too — so the tab never flips on load.
            'seo' => array_filter([
                'title' => document_title($title),
                'description' => $description,
                'keywords' => $keywords,
                'robots' => $ulid !== null ? 'noindex, nofollow' : null,
            ]),
        ];
    }

    /**
     * Head payload for a shared conversation page (/share/{token}).
     *
     * Share links are unlisted-but-public: anyone holding the token can read the
     * conversation, but that is not the same as consenting to it being indexed and
     * surfaced in search results. Keeping crawlers out does not affect the link, which
     * still works for anyone it was given to.
     */
    public static function sharedProps(): array
    {
        return [
            'seo' => [
                'robots' => 'noindex, nofollow',
            ],
        ];
    }

    /**
     * Read an addon setting, normalizing blank/whitespace-only to null so an emptied
     * field falls back to the default rather than emitting an empty tag.
     */
    private static function setting(string $key): ?string
    {
        $value = trim((string) addon_setting('ai-chatbot', $key, ''));

        return $value === '' ? null : $value;
    }
}
