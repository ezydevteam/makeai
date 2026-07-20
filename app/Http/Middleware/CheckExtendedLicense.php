<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Gates routes that must work whenever an Extended License is present, even if
 * the admin has switched the subscriptions feature toggle off — e.g. cancelling
 * an existing subscription or completing an already-initiated payment. Blocking
 * these behind the toggle would trap users who are still being billed.
 */
class CheckExtendedLicense
{
    public function handle(Request $request, Closure $next)
    {
        if (! is_extended_license()) {
            abort(404);
        }

        return $next($request);
    }
}
