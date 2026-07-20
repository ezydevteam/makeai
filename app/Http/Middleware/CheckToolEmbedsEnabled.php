<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckToolEmbedsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) settings('tool_embeds_enabled', true)) {
            abort(404);
        }

        return $next($request);
    }
}
