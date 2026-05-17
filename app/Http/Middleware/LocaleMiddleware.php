<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LocaleMiddleware
{
    /**
     * Handle an incoming request and set the application locale.
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = Session::get('locale', settings('default_language', config('app.locale', 'en')));

        // Check if user has a preferred locale (overrides session)
        if ($request->user()) {
            $locale = $request->user()->language ?? $locale;
        }

        App::setLocale($locale);

        return $next($request);
    }
}
