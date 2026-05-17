<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AdminAuth — ensures the request is from an authenticated, active admin.
 *
 * Applied to all admin panel routes.
 */
class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin')->user();

        if (! $admin) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('admin.login');
        }

        if (! $admin->is_active) {
            auth('admin')->logout();
            $request->session()->invalidate();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Account deactivated.'], 403);
            }

            return redirect()->route('admin.login')
                ->with('error', translate('Your admin account has been deactivated.'));
        }

        return $next($request);
    }
}
