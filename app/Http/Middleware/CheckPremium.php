<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPremium
{
    public function handle(Request $request, Closure $next)
    {
        if (! isProAvailable()) {
            abort(404);
        }

        return $next($request);
    }
}
