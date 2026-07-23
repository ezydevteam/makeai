<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotBanned
{
    public function handle(Request $request, Closure $next)
    {
        // User bans apply to the frontend 'web' guard only. The admin panel runs on the
        // separate 'admin' guard, so skip admin routes entirely — otherwise a stray
        // banned web session in the same browser could bounce an admin out of the panel.
        if ($request->is('admin', 'admin/*')) {
            return $next($request);
        }

        $user = Auth::guard('web')->user();

        if ($user && $user->is_banned) {
            $message = translate('Your account has been suspended. Please contact support.');

            // Log out ONLY the web guard. Do NOT call session()->invalidate(): the web
            // and admin guards share one session, so wiping it would also sign out an
            // admin who is signed in on the same browser. Guard-scoped logout clears the
            // banned user's auth while leaving any admin-guard session intact.
            Auth::guard('web')->logout();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 403);
            }

            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
