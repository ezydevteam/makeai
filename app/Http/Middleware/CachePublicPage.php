<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CachePublicPage — opt-in browser caching for public, read-only content pages
 * (blog, tool directory, CMS pages, home).
 *
 * Why `private` and not `public`/CDN-shared: the rendered HTML embeds a
 * session-bound CSRF token (`<meta name="csrf-token">`) and the visitor's shared
 * Inertia props. A shared cache would hand one guest's token to another → 419 on
 * the next form POST. `private` scopes the copy to the requesting browser, so a
 * returning guest reuses only *their own* page (with their own valid token) — a
 * real win for repeat views and back/forward navigation with zero CSRF hazard.
 *
 * Guardrails:
 *  - GET/HEAD, 200 only.
 *  - Authenticated users (user or admin) are skipped — their pages are personalized.
 *  - TTL comes from the `public_page_cache_ttl` setting (seconds). 0 disables it,
 *    so a buyer can turn caching off without a code change.
 */
class CachePublicPage
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodCacheable()) {
            return $response;
        }

        // Personalized surfaces must never be cached, even privately.
        if (auth()->check() || auth('admin')->check()) {
            return $response;
        }

        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $ttl = (int) $this->ttl();
        if ($ttl <= 0) {
            return $response;
        }

        // Don't clobber a Cache-Control a controller already set deliberately.
        if ($response->headers->has('Cache-Control')
            && $response->headers->get('Cache-Control') !== 'no-cache, private') {
            return $response;
        }

        $response->headers->set('Cache-Control', "private, max-age={$ttl}");
        // Vary so a browser/proxy keeps guest and (future) authed copies distinct,
        // and distinguishes Inertia XHR JSON from full-page HTML for the same URL.
        $response->headers->set('Vary', 'Cookie, X-Inertia, Accept');

        return $response;
    }

    private function ttl(): int
    {
        try {
            return (int) settings('public_page_cache_ttl', 300);
        } catch (\Throwable) {
            return 300;
        }
    }
}
