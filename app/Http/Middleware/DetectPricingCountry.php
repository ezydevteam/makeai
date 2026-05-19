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
            $request->session()->put('pricing_country', $country);
            $request->attributes->set('pricing_country', $country);
        }

        return $next($request);
    }
}
