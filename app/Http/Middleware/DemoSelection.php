<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Services\DemoSelectionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the demo bar's preset/addon selection for the current request.
 *
 * Reads the `demo_selection` cookie (set by the GET-only DemoController::select),
 * asks DemoSelectionResolver what settings that choice overrides, and installs them
 * in memory via Setting::overrideForRequest(). Everything downstream — the settings()
 * helper, ThemeSettingsService, HomeController, the theme CSS route — then resolves the
 * chosen demo automatically. Nothing is persisted.
 *
 * MUST run before HandleInertiaRequests, which eagerly resolves the theme/homepage
 * settings it shares as props. Gated on config('demo.enabled') so it is a complete
 * no-op in production.
 */
class DemoSelection
{
    public function __construct(private DemoSelectionResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('demo.enabled')) {
            return $next($request);
        }

        $key = (string) $request->cookie('demo_selection', '');

        if ($key !== '') {
            $overrides = $this->resolver->overridesFor($key);

            if ($overrides !== []) {
                Setting::overrideForRequest($overrides);
            }
        }

        return $next($request);
    }
}
