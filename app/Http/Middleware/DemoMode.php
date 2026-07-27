<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DemoMode
{
    /**
     * Route names explicitly allowed for write operations in demo mode.
     *
     * These must be the names of the routes that actually RECEIVE the write.
     * 'login' and 'admin.login' are the GET form routes, so listing them allowed
     * nothing — the middleware only ever inspects POST/PUT/PATCH/DELETE. The POST
     * handlers are '<name>.attempt', and they were missing, which blocked every
     * login in demo mode. 'admin.login.otp' and 'admin.login.verify' named routes
     * that do not exist at all; admin two-factor is 'admin.2fa.verify'.
     */
    protected array $allowedRouteNames = [
        // Signing in — authentication is not a destructive write.
        'login.attempt',
        'logout',
        'two-factor.verify',
        'two-factor.resend',
        'admin.login.attempt',
        'admin.logout',
        'admin.2fa.verify',
        // Presentation-only preferences.
        'locale.switch',
        'profile.theme',
        // Ad impression/click counters. These only increment analytics totals — nothing
        // is created or destroyed — and AdSection fires the view POST on every page that
        // renders an ad, so blocking them filled the console with 403s and froze the ad
        // stats the demo dashboard reports. Both are already IP rate-limited at the route.
        'ads.trackView',
        'ads.trackClick',
        // Allowed through the generic block so RegisterController::register() can
        // reject it with a registration-specific "disabled in demo mode" message.
        'register.attempt',
        // Read-only downloads that happen to be POSTs. They stream a spreadsheet built
        // from what the account can already see and write nothing, so the generic
        // destructive-method block was hiding a working feature behind a 403 — and the
        // client saved the JSON error body as a .xlsx.
        'user.dashboard.usage.export',
        'user.dashboard.export',
    ];

    /**
     * Allowed route names that an ADDON registers, so they exist only when that addon is
     * installed and active.
     *
     * Kept apart from $allowedRouteNames because DemoModeTest asserts every name in that
     * list resolves — the check that caught 'admin.login.otp' naming a route that never
     * existed. An addon route legitimately does not resolve on an install without the
     * addon, so holding these here keeps the strict guard meaningful for core routes
     * instead of having to weaken it for everything.
     */
    protected array $allowedAddonRouteNames = [
        // The Knowledge Base's AI answer. A POST only because the query travels in the
        // body; the one row it writes is the search-log entry the analytics screen reads.
        // Matched by NAME rather than path because the KB's public prefix is an admin
        // setting (`public_slug`) — 'help/search' would stop matching the moment someone
        // renamed it. Metered per IP in KbSearchService and backstopped by the global
        // daily budget, so allowing it here does not make it unbounded.
        'addon.kb.public.search',
    ];

    /**
     * URI prefixes/patterns allowed for write operations in demo mode.
     */
    protected array $allowedUriPatterns = [
        'api/v1/generate/stream',
        'api/v1/generate/text',
        'api/v1/generate/estimate',
        'api/v1/ai/chat',
        'api/v1/ai/complete',
        'api/v1/tools/',
        'live-search',
    ];

    /**
     * Writes allowed by exact METHOD + path, for APIs where a prefix is too blunt.
     *
     * The AI Chatbot's whole API lives under `api/v1/chat`, so listing that prefix in
     * $allowedUriPatterns waved through all 27 of its routes — including DELETE on a
     * conversation, a project and a tag, plus rename, pin, share and settings. A demo
     * visitor could permanently empty the seeded chat library, and nothing said no.
     *
     * These are what "try the product" actually needs; everything else in those APIs is a
     * mutation of stored content and is blocked like any other write.
     *
     * Matched against "METHOD path" as an anchored regex.
     */
    protected array $allowedWritePatterns = [
        // Chatbot: start a conversation, send a message (the generation itself), attach a
        // file to it.
        'POST api/v1/chat',
        'POST api/v1/chat/[^/]+/message',
        'POST api/v1/chat/attachments',

        // AI Image Pro: the free local toolbox (resize, crop, compress, convert, watermark)
        // and the upload it operates on. These run through GD on this server, cost nothing
        // per call and are the addon's top-of-funnel draw, so a demo that refuses them shows
        // the visitor a wall instead of the product.
        //
        // `POST ai-image/generate` and `POST ai-image/ops/*` are deliberately NOT here: both
        // call a paid provider, so every visitor click would spend the operator's money.
        // They stay blocked and now say so (the addon toasts the refusal).
        'POST ai-image/upload',
        'POST ai-image/tools/[^/]+',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('demo.enabled')) {
            return $next($request);
        }

        $destructiveMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        if (! in_array($request->method(), $destructiveMethods)) {
            return $next($request);
        }

        if ($this->isWriteAllowed($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => translate('This action is disabled in demo mode.')], 403);
        }

        return back()->with('error', translate('This action is disabled in demo mode.'));
    }

    /**
     * Determine whether the current destructive request should be allowed through.
     */
    protected function isWriteAllowed(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if ($routeName && in_array($routeName, $this->allowedRouteNames, true)) {
            return true;
        }

        if ($routeName && in_array($routeName, $this->allowedAddonRouteNames, true)) {
            return true;
        }

        $uri = $request->path();

        foreach ($this->allowedUriPatterns as $pattern) {
            if (str_starts_with($uri, $pattern)) {
                return true;
            }
        }

        $signature = $request->method() . ' ' . $uri;

        foreach ($this->allowedWritePatterns as $pattern) {
            if (preg_match('#^' . $pattern . '$#', $signature) === 1) {
                return true;
            }
        }

        return false;
    }
}
