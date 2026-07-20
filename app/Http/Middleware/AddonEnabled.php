<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces an addon's admin "Enabled" setting server-side. When an admin turns a
 * feature off, its routes must be unreachable — not merely hidden in the menu,
 * which a direct URL would bypass.
 *
 * Usage: ->middleware('addon.enabled:ai-image-editor')
 */
class AddonEnabled
{
    public function handle(Request $request, Closure $next, string $slug): Response
    {
        if (! (bool) addon_setting($slug, 'enabled', true)) {
            abort(404);
        }

        return $next($request);
    }
}
