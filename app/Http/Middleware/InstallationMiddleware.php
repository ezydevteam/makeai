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
     * - When INSTALLED=true: /install/* returns 404.
     * - When INSTALLED=false: redirects to /install.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $installed = filter_var(env('INSTALLED', false), FILTER_VALIDATE_BOOLEAN);

        if ($request->is('install') || $request->is('install/*')) {
            if ($installed) {
                abort(404);
            }
            return $next($request);
        }

        if (! $installed && ! file_exists(base_path('.env'))) {
            return redirect('/install');
        }

        if (! $installed) {
            return redirect('/install');
        }

        return $next($request);
    }
}
