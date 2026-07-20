<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountDeletionEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) settings('account_deletion_enabled', true)) {
            abort(404);
        }

        return $next($request);
    }
}
