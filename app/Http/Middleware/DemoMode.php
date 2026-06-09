<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DemoMode
{
    /**
     * Route names explicitly allowed for write operations in demo mode.
     */
    protected array $allowedRouteNames = [
        'login',
        'logout',
        'admin.login',
        'admin.logout',
        'admin.login.otp',
        'admin.login.verify',
        'locale.switch',
        'profile.theme',
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
        'api/v1/chat',
        'api/v1/tools/',
        'live-search',
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
            return response()->json(['message' => translate('Destructive actions are disabled in demo mode.')], 403);
        }

        return back()->with('error', translate('Destructive actions are disabled in demo mode.'));
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

        $uri = $request->path();

        foreach ($this->allowedUriPatterns as $pattern) {
            if (str_starts_with($uri, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
