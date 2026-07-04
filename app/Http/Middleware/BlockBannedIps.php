<?php

namespace App\Http\Middleware;

use App\Services\RateLimiterService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockBannedIps
{
    public function __construct(private RateLimiterService $rateLimiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Never block the admin panel. An "entire site" ban targets abusive
        // visitors, not staff — and exempting /admin/* guarantees an admin who
        // accidentally bans their own (or a shared CGNAT) IP can still reach the
        // panel to remove it, so a site-wide ban can never be a self-lockout.
        if ($request->is('admin', 'admin/*')) {
            return $next($request);
        }

        if (! $this->rateLimiter->isIpBlockedSiteWide($request->ip())) {
            return $next($request);
        }

        $message = translate('Your IP address has been blocked from accessing this site.');

        if ($request->expectsJson() || $request->header('X-Inertia')) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        // Minimal self-contained page — no assets, layout, or extra queries, so a
        // banned IP hammering the site stays cheap to serve.
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<title>Access blocked</title><style>'
            .'body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#0f172a;'
            .'color:#e2e8f0;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}'
            .'.box{text-align:center;padding:2rem}h1{font-size:3.5rem;margin:0 0 .5rem;color:#f87171}'
            .'p{color:#94a3b8;max-width:24rem}</style></head><body><div class="box">'
            .'<h1>403</h1><p>'.e($message).'</p></div></body></html>';

        return response($html, 403);
    }
}
