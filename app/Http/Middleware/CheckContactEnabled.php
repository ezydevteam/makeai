<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckContactEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) settings('contact_enabled', true)) {
            abort(404);
        }

        return $next($request);
    }
}
