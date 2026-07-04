<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckSuperAdmin — restricts a route to Super Admins only.
 *
 * Used for irreversible / high-risk operations (e.g. permanent "force" deletes)
 * that must never be delegated to sub-admin roles, even ones granted the
 * corresponding *.delete permission.
 */
class CheckSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth('admin')->user();

        if (! $admin || ! $admin->isSuperAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => translate('This action is restricted to Super Admins.'),
                ], 403);
            }

            abort(403, translate('This action is restricted to Super Admins.'));
        }

        return $next($request);
    }
}
