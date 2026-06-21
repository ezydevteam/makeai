<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTicketsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) settings('tickets_enabled', true)) {
            abort(404);
        }

        return $next($request);
    }
}
