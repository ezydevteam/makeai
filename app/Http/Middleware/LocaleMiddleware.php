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
     *
     * Every candidate is checked against the cached active-code whitelist before it can
     * reach App::setLocale(). Both reads are cache-backed, so the hot path costs no
     * queries; the previous version ran an uncached languages lookup (plus a second on
     * miss) on every request, and could fall through to an unvalidated code when the
     * lookup threw or no row was marked default.
     */
    public function handle(Request $request, Closure $next)
    {
        App::setLocale($this->resolveLocale($request));

        return $next($request);
    }

    /**
     * Resolve the request's locale, narrowing to a known-active code.
     *
     * Precedence (unchanged): the signed-in user's saved preference, then a manually
     * chosen session locale, then the site default.
     */
    private function resolveLocale(Request $request): string
    {
        $activeCodes = Language::activeCodes();

        foreach ($this->candidates($request) as $candidate) {
            if (is_string($candidate) && in_array($candidate, $activeCodes, true)) {
                return $candidate;
            }
        }

        // Nothing requested was active. Prefer the site default, but only if it is itself
        // active — a default row can be deactivated, and defaultCode() falls back to
        // config when no row is marked default at all.
        $default = Language::defaultCode();

        if (in_array($default, $activeCodes, true)) {
            return $default;
        }

        // Final floor: a trusted config value, never request input. Reached mid-install
        // (no rows/cache yet) or if the default language has been deactivated.
        return config('app.locale', 'en');
    }

    /**
     * Locale candidates in precedence order. Values are untrusted here — the caller
     * validates them — though both are written through LocaleController::switch, which
     * already checks the code exists and is active.
     *
     * @return array<int, string|null>
     */
    private function candidates(Request $request): array
    {
        $userLocale = null;

        try {
            $userLocale = $request->user()?->locale;
        } catch (\Throwable $e) {
            // Session/DB not ready (e.g. mid-install) — fall through to the session value.
        }

        return [
            $userLocale,
            Session::get('locale_manually_selected') ? Session::get('locale') : null,
        ];
    }
}
