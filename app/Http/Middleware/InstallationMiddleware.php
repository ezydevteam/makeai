<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InstallationMiddleware
{
    /**
     * Gating middleware for the installation wizard.
     *
     * - When INSTALLED=true:  /install/* returns 404.
     * - When INSTALLED=false: all requests pass through
     *   (the wizard handles its own step logic).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $installed = env('INSTALLED', false);

        // String 'true'/'1' from .env is truthy; handle it explicitly
        if ($installed && $installed !== 'false' && $installed !== '0') {
            if ($request->is('install', 'install/*')) {
                abort(404);
            }
        }

        return $next($request);
    }
}
