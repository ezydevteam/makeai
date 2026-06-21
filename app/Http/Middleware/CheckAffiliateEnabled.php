<?php

namespace App\Http\Middleware;

use App\Services\AffiliateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAffiliateEnabled
{
    public function __construct(private readonly AffiliateService $affiliate) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->affiliate->isEnabled()) {
            abort(404);
        }

        return $next($request);
    }
}
