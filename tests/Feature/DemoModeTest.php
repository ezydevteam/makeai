<?php

namespace Tests\Feature;

use App\Http\Middleware\DemoMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\TestCase;

/**
 * Demo mode blocks every write except an explicit allowlist of route NAMES.
 * That allowlist is the fragile part: a name that is misspelled, renamed, or
 * points at the GET form route instead of its POST handler fails silently —
 * nothing errors, the route is simply never matched and the write is blocked.
 *
 * That is exactly how login broke: the list held 'login' and 'admin.login',
 * which are the GET routes, while the POST handlers ('login.attempt',
 * 'admin.login.attempt') were absent. Demo mode rejected every sign-in with
 * "Destructive actions are disabled in demo mode." Two further entries,
 * 'admin.login.otp' and 'admin.login.verify', named routes that never existed.
 */
class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    private function allowedRouteNames(): array
    {
        $property = (new ReflectionClass(DemoMode::class))->getProperty('allowedRouteNames');
        $property->setAccessible(true);

        return $property->getValue(new DemoMode());
    }

    public function test_every_allowlisted_route_name_exists(): void
    {
        $missing = [];

        foreach ($this->allowedRouteNames() as $name) {
            if (! Route::has($name)) {
                $missing[] = $name;
            }
        }

        $this->assertSame([], $missing, 'Demo mode allows route names that do not exist: ' . implode(', ', $missing));
    }

    /**
     * @return array<int, string>
     */
    private function allowedAddonRouteNames(): array
    {
        $property = (new ReflectionClass(DemoMode::class))->getProperty('allowedAddonRouteNames');
        $property->setAccessible(true);

        return $property->getValue(new DemoMode());
    }

    /**
     * Addon-provided names cannot be asserted to exist — the addon may not be installed,
     * which is the whole reason they live in a separate list. What CAN be asserted is that
     * each one is namespaced under `addon.`, so a core route never hides in the lenient
     * list to dodge the existence check above.
     */
    public function test_addon_allowlist_holds_only_addon_route_names(): void
    {
        $misplaced = array_values(array_filter(
            $this->allowedAddonRouteNames(),
            fn (string $name) => ! str_starts_with($name, 'addon.'),
        ));

        $this->assertSame([], $misplaced, 'Core route names must live in $allowedRouteNames, where their existence is checked: ' . implode(', ', $misplaced));
    }

    /**
     * The allowlist is consulted by NAME, so a registered addon route with a listed name is
     * allowed through while its neighbours are not. Registered here rather than depending on
     * the KB addon being installed in the test environment.
     */
    public function test_allowlisted_addon_route_is_permitted(): void
    {
        config(['demo.enabled' => true]);

        Route::post('help/search', fn () => response()->json(['ok' => true]))
            ->middleware(DemoMode::class)
            ->name('addon.kb.public.search');
        Route::post('help/vote/{article}', fn () => response()->json(['ok' => true]))
            ->middleware(DemoMode::class)
            ->name('addon.kb.public.vote');

        $this->postJson('/help/search', ['query' => 'credits'])->assertOk();
        $this->postJson('/help/vote/01ABC', ['vote' => 1])->assertForbidden();
    }

    /**
     * An allowlisted name only does something if that route accepts a write
     * method — DemoMode never inspects GET requests, so a GET-only entry is
     * dead weight that reads as protection while granting none.
     */
    public function test_every_allowlisted_route_accepts_a_write_method(): void
    {
        $writeMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        $readOnly = [];

        foreach ($this->allowedRouteNames() as $name) {
            if (! Route::has($name)) {
                continue; // reported by the test above
            }

            $methods = Route::getRoutes()->getByName($name)->methods();

            if (array_intersect($writeMethods, $methods) === []) {
                $readOnly[] = $name . ' [' . implode(',', $methods) . ']';
            }
        }

        $this->assertSame([], $readOnly, 'Demo mode allowlists read-only routes, which grants nothing: ' . implode(', ', $readOnly));
    }

    /**
     * The specific regression: signing in must survive demo mode.
     */
    public function test_sign_in_routes_are_allowed_in_demo_mode(): void
    {
        $allowed = $this->allowedRouteNames();

        foreach (['login.attempt', 'admin.login.attempt', 'logout', 'admin.logout'] as $name) {
            $this->assertContains($name, $allowed, "Demo mode must allow [{$name}] or nobody can sign in.");
        }
    }

    public function test_demo_mode_blocks_an_unlisted_write(): void
    {
        config(['demo.enabled' => true]);

        $request = \Illuminate\Http\Request::create('/some/destructive/path', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = (new DemoMode())->handle($request, fn () => response('reached handler'));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('demo mode', (string) $response->getContent());
    }

    public function test_demo_mode_is_inert_when_disabled(): void
    {
        config(['demo.enabled' => false]);

        $request = \Illuminate\Http\Request::create('/some/destructive/path', 'POST');

        $response = (new DemoMode())->handle($request, fn () => response('reached handler'));

        $this->assertSame('reached handler', (string) $response->getContent());
    }
}
