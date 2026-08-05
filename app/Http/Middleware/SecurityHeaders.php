<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders — adds OWASP-recommended response headers site-wide.
 *
 * Design notes (this is a resold product, so defaults must not break buyer setups):
 *  - The always-safe headers (nosniff, frame-options, referrer/permissions policy,
 *    HSTS) are sent unconditionally on every response.
 *  - CSP is intentionally permissive by default: the platform loads first-party
 *    scripts from MANY third parties depending on which features a buyer enables —
 *    Stripe/PayPal/Razorpay/Paddle/SSLCommerz/Paystack/CoinGate JS, Google AdSense &
 *    Analytics, and arbitrary admin-injected "custom header code". A strict allowlist
 *    would silently break those. The baseline still blocks the high-value stuff
 *    (object/embed, base-uri hijack, non-HTTPS resources, framing by third parties).
 *  - CSP is only emitted on HTML responses and can be disabled via the
 *    `security_csp_enabled` setting (default on). The embed widget sets its own CSP
 *    with a route-scoped `frame-ancestors`, so this middleware never touches it.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // The embed widget manages its own framing/CSP policy (origin allowlist).
        if ($this->isEmbedRequest($request)) {
            return $response;
        }

        $headers = $response->headers;

        // Always-safe headers — cheap, no compatibility risk.
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');

        if (! $headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        // HSTS is only honoured by browsers over HTTPS, so it is safe to send whenever
        // the site is served/configured for HTTPS (real request scheme or APP_URL).
        if ($this->isHttps($request)) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // CSP: HTML responses only, never overriding a route that already set one.
        // Skipped while the Vite dev server is hot (`npm run dev`): it streams JS
        // modules + HMR over plain HTTP/WS from a non-'self' origin (e.g.
        // http://[::1]:5173) that the strict production policy blocks. CSP is a
        // production hardening concern, so relaxing it in local dev is safe — the
        // header still ships on every real (built-asset) deployment.
        if ($this->cspEnabled()
            && ! $this->isViteHot()
            && $this->isHtml($response)
            && ! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        return $response;
    }

    private function isEmbedRequest(Request $request): bool
    {
        return $request->is('embed', 'embed/*');
    }

    private function isHttps(Request $request): bool
    {
        if ($request->secure()) {
            return true;
        }

        return str_starts_with(strtolower((string) config('app.url')), 'https://');
    }

    private function isHtml(Response $response): bool
    {
        $contentType = (string) $response->headers->get('Content-Type');

        // Empty content-type on a redirect/streamed HTML page still counts as a page.
        return $contentType === '' || str_contains(strtolower($contentType), 'text/html');
    }

    /**
     * Whether the Vite dev server is running (`npm run dev`). Laravel writes a
     * `public/hot` file for the lifetime of that process; its absence means the
     * app is serving fingerprinted built assets (production / `npm run build`).
     */
    private function isViteHot(): bool
    {
        return is_file(public_path('hot'));
    }

    private function cspEnabled(): bool
    {
        try {
            return (bool) settings('security_csp_enabled', true);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Permissive-but-meaningful baseline CSP. `https:` in script/connect/frame keeps
     * every payment gateway, analytics tag and admin custom code working while still
     * denying plaintext http, plugins/objects, base-uri hijacking and third-party
     * framing. Buyers who run a locked-down deployment can tighten via a future setting.
     */
    private function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:",
            "script-src-elem 'self' 'unsafe-inline' 'unsafe-eval' https:",
            "style-src 'self' 'unsafe-inline' https:",
            "style-src-elem 'self' 'unsafe-inline' https:",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https:",
            "connect-src 'self' https: wss:",
            "frame-src 'self' https:",
            "media-src 'self' data: blob: https:",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self' https:",
            "frame-ancestors 'self'",
        ]);
    }
}
