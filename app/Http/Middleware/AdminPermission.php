<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AdminPermission — checks if the authenticated admin has the required permission.
 *
 * Usage in routes: ->middleware('admin.permission:users.edit')
 */
class AdminPermission
{
    /**
     * Accepts one or more permission slugs.
     * When multiple are given, access is granted if the admin has ANY of them (OR logic).
     *
     * Usage: ->middleware('admin.permission:users.edit')
     *    or: ->middleware('admin.permission:settings.general,settings.manage')
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $admin = auth('admin')->user();

        if (! $admin) {
            abort(401);
        }

        if (! $admin->hasAnyPermission($permissions)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You do not have permission to perform this action.',
                ], 403);
            }

            abort(403, translate('You do not have permission to perform this action.'));
        }

        return $next($request);
    }
}
