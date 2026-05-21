<?php

namespace App\Http\Middleware;

use App\Models\Language;
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
        $defaultLocale = settings('default_language', config('app.locale', 'en'));
        $locale = Session::get('locale_manually_selected')
            ? Session::get('locale', $defaultLocale)
            : $defaultLocale;

        // Check if user has a preferred locale (overrides session)
        if ($request->user()) {
            $locale = $request->user()->locale ?? $locale;
        }

        $language = Language::query()
            ->where('code', $locale)
            ->where('is_active', true)
            ->first() ?: Language::getDefault();
        $locale = $language?->code ?? settings('default_language', config('app.locale', 'en'));

        App::setLocale($locale);

        return $next($request);
    }
}
