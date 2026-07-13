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
        // Default language lives in the languages.is_default column (single
        // source of truth); defaultCode() is self-guarding when the DB isn't ready.
        $defaultLocale = Language::defaultCode();

        $locale = Session::get('locale_manually_selected')
            ? Session::get('locale', $defaultLocale)
            : $defaultLocale;

        // Check if user has a preferred locale (overrides session)
        try {
            if ($request->user()) {
                $locale = $request->user()->locale ?? $locale;
            }
        } catch (\Throwable $e) {
            // DB not ready/installed
        }

        try {
            $language = Language::query()
                ->where('code', $locale)
                ->where('is_active', true)
                ->first();
            if (! $language) {
                $language = Language::getDefault();
            }
            $locale = $language?->code ?? $locale;
        } catch (\Throwable $e) {
            // Table/DB not ready
        }

        App::setLocale($locale);

        return $next($request);
    }
}
