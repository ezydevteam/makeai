<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NotBanned
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->is_banned) {
            return response()->json([
                'success' => false,
                'message' => translate('Your account has been suspended. Please contact support.'),
            ], 403);
        }

        return $next($request);
    }
}
