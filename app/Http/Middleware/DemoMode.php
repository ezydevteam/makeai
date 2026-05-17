<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DemoMode
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.demo')) {
            $destructiveMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
            $isDestructive = in_array($request->method(), $destructiveMethods);

            // Allow login/logout and some specific routes
            $allowedRoutes = ['login', 'logout', 'admin.login', 'admin.logout'];
            $isAllowed = in_array($request->route()?->getName(), $allowedRoutes);

            if ($isDestructive && ! $isAllowed) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Destructive actions are disabled in demo mode.'], 403);
                }

                return back()->with('error', 'Destructive actions are disabled in demo mode.');
            }
        }

        return $next($request);
    }
}
