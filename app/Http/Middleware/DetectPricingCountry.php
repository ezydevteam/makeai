<?php

namespace App\Http\Middleware;

use App\Services\Pricing\PricingCountryDetector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectPricingCountry
{
    public function handle(Request $request, Closure $next): Response
    {
        if (isProAvailable()) {
            $country = app(PricingCountryDetector::class)->detect($request);
            $request->attributes->set('pricing_country', $country);

            // Drop the legacy per-session cache: it used to persist a *defaulted*
            // country (e.g. a stale "US") that then won over the live default and
            // never recovered. Detection is now recomputed per request.
            if ($request->hasSession() && $request->session()->has('pricing_country')) {
                $request->session()->forget('pricing_country');
            }
        }

        return $next($request);
    }
}
