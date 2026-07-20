/**
 * Compose the page-portion of a document title for public frontend pages:
 *   - "Heading - meta_title"  when a distinct SEO meta_title override exists
 *   - "Heading"               otherwise
 *
 * The global Inertia title callback (see resources/js/app.ts) then appends
 * " | <site name>", producing the final "Heading - meta_title | Site" or
 * "Heading | Site". Keeping the site-name suffix in the callback means every
 * page here stays site-name-agnostic and can never hardcode/duplicate it.
 */
export function composePageTitle(heading?: string | null, metaTitle?: string | null): string {
    const h = (heading ?? '').trim()
    const m = (metaTitle ?? '').trim()

    if (m && m !== h) {
        return h ? `${h} - ${m}` : m
    }

    return h
}
