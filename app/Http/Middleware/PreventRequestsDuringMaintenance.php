<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PreventRequestsDuringMaintenance extends \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance
{
    protected $except = [
        'admin',
        'admin/*',
    ];

    public function handle($request, Closure $next): Response
    {
        if ($this->app->maintenanceMode()->active()) {
            $secret = (string) settings('maintenance_bypass_secret', '');

            if ($secret !== '' && hash_equals($secret, (string) $request->query('secret', ''))) {
                return $this->bypassResponse($secret);
            }

            if ($this->ipCanBypass($request)) {
                return $next($request);
            }
        }

        return parent::handle($request, $next);
    }

    private function ipCanBypass(Request $request): bool
    {
        $allowedIps = collect(explode(',', (string) settings('maintenance_allowed_ips', '')))
            ->map(fn (string $ip): string => trim($ip))
            ->filter()
            ->values();

        if ($allowedIps->isEmpty()) {
            return false;
        }

        $requestIp = (string) $request->ip();

        return $allowedIps->contains(fn (string $allowedIp): bool => $allowedIp === $requestIp
            || (Str::endsWith($allowedIp, '.*') && Str::startsWith($requestIp, Str::beforeLast($allowedIp, '.*').'.')));
    }
}
