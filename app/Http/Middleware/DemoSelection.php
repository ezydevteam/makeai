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

        $this->applyBlogLayout($request);

        return $next($request);
    }

    /**
     * Apply the ?demo_blog=<key> layout from the demo nav's Blog group.
     *
     * Here rather than in HandleInertiaRequests, where its ?demo_hero / ?demo_tool siblings
     * live: those two rewrite props the Inertia middleware builds itself, but the blog
     * layout is read by BlogController through settings() — which has already run by the
     * time props are shared. Installed as request overrides, it reaches the controller.
     *
     * The item carries a whole settings map rather than one named key, so a layout that
     * needs two settings to agree (an archive with no sidebar AND a centered article) is
     * one menu entry, and adding another needs no code here.
     */
    private function applyBlogLayout(Request $request): void
    {
        $key = (string) $request->query('demo_blog', '');

        if ($key === '') {
            return;
        }

        $item = collect(config('demo.nav.blog.items', []))->firstWhere('key', $key);
        $settings = $item['settings'] ?? null;

        if (is_array($settings) && $settings !== []) {
            Setting::overrideForRequest($settings);
        }
    }
}
