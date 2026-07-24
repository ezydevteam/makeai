<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\DemoSelectionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The demo switcher has two independent, GET-only mechanisms, both of which must be
 * completely inert when demo mode is off:
 *   - Nav menu: ?demo_home / ?demo_tool swap a page-level layout variant only.
 *   - Selector: a cookie-driven, in-memory setting override for a preset/addon.
 *
 * Nothing here may persist — demo mode blocks writes — so these tests pin the
 * read-only, cookie/query-param design and the production no-op guarantee.
 */
class DemoSwitcherTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Setting::clearOverrides();
        parent::tearDown();
    }

    // ─── Selector route ───

    public function test_select_route_404s_when_demo_disabled(): void
    {
        config(['demo.enabled' => false]);

        $this->get('/__demo/select?demo=preset:midnight')->assertNotFound();
    }

    public function test_select_route_sets_cookie_for_a_valid_preset(): void
    {
        config(['demo.enabled' => true]);

        $response = $this->get('/__demo/select?demo=preset:midnight');

        $response->assertRedirect();
        $response->assertCookie('demo_selection', 'preset:midnight');
    }

    public function test_select_route_clears_cookie_for_default(): void
    {
        config(['demo.enabled' => true]);

        $this->get('/__demo/select?demo=default')->assertCookieExpired('demo_selection');
    }

    public function test_select_route_ignores_an_unknown_selection(): void
    {
        config(['demo.enabled' => true]);

        // Not on the catalog → treated like "default": the cookie is cleared, never set.
        $this->get('/__demo/select?demo=preset:does-not-exist')
            ->assertCookieExpired('demo_selection');
    }

    // ─── Nav menu (page-level layout only) ───

    public function test_demo_home_query_overrides_hero_variant(): void
    {
        config(['demo.enabled' => true]);

        $this->get('/?demo_home=home-2')->assertInertia(
            fn (Assert $page) => $page->where('frontendHomepageSettings.hero_variant', 'tools-grid')
        );
    }

    public function test_demo_home_query_is_ignored_when_demo_disabled(): void
    {
        config(['demo.enabled' => false]);

        // With demo off the query param must not reshuffle the homepage.
        $this->get('/?demo_home=home-2')->assertInertia(
            fn (Assert $page) => $page->where(
                'frontendHomepageSettings.hero_variant',
                fn ($variant) => $variant !== 'tools-grid'
            )
        );
    }

    public function test_demo_bar_prop_absent_when_demo_disabled(): void
    {
        config(['demo.enabled' => false]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('demoBar', null));
    }

    public function test_demo_bar_prop_present_when_demo_enabled(): void
    {
        config(['demo.enabled' => true]);

        // The FAB payload is just the preset/addon catalog + active key — the nav itself
        // rides in globalMenus (asserted separately below).
        $this->get('/')->assertInertia(
            fn (Assert $page) => $page->has('demoBar.selectable')
                ->has('demoBar.active')
        );
    }

    public function test_demo_nav_is_injected_into_the_header_menu_when_enabled(): void
    {
        config(['demo.enabled' => true]);

        $this->get('/')->assertInertia(function (Assert $page) {
            $menus = collect($page->toArray()['props']['globalMenus'] ?? []);
            $main = $menus->firstWhere('slug', 'main');

            $this->assertNotNull($main, 'Demo mode should ensure a "main" menu exists to host the nav.');

            $labels = collect($main['items'])->pluck('label');
            $this->assertTrue($labels->contains('Home'), 'Header menu should carry the demo Home parent.');
            $this->assertTrue($labels->contains('Tool Page'), 'Header menu should carry the demo Tool Page parent.');

            // A child links through ?demo_home so it changes only the target-page layout.
            $home1 = collect($main['items'])->firstWhere('label', 'Home 1 — Gradient');
            $this->assertNotNull($home1);
            $this->assertSame('demo-home', $home1['parent_id']);
            $this->assertStringContainsString('demo_home=home-1', $home1['url']);
        });
    }

    public function test_demo_nav_is_absent_from_the_header_menu_when_disabled(): void
    {
        config(['demo.enabled' => false]);

        $this->get('/')->assertInertia(function (Assert $page) {
            $menus = collect($page->toArray()['props']['globalMenus'] ?? []);
            $labels = $menus->flatMap(fn ($m) => collect($m['items'] ?? [])->pluck('label'));

            $this->assertFalse($labels->contains('Tool Page'), 'Demo nav must not leak into a real menu when demo is off.');
        });
    }

    // ─── Resolver ───

    public function test_resolver_builds_preset_overrides(): void
    {
        $resolver = app(DemoSelectionResolver::class);

        $this->assertTrue($resolver->isValid('preset:midnight'));

        $overrides = $resolver->overridesFor('preset:midnight');

        // Preset colours land under the theme-settings key, and the homepage owner is
        // pinned back to the theme so a preset never inherits an addon homepage.
        $this->assertArrayHasKey('frontend_theme_settings', $overrides);
        $this->assertSame('default', $overrides['homepage_template']);
    }

    public function test_resolver_auto_picks_a_dropped_in_screenshot(): void
    {
        $dir = public_path('assets/image/demos');
        $file = $dir . '/midnight.png';
        $preexisting = file_exists($file);

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (! $preexisting) {
            file_put_contents($file, 'fake-png');
        }

        try {
            $catalog = app(DemoSelectionResolver::class)->catalog();
            $midnight = collect($catalog)->firstWhere('key', 'preset:midnight');

            $this->assertNotNull($midnight);
            $this->assertSame('/assets/image/demos/midnight.png', $midnight['image']);

            // A preset with no screenshot dropped in reports null, so the UI falls back
            // to its icon placeholder.
            $unknown = app(DemoSelectionResolver::class);
            $this->assertNull($this->invokeDemoImage($unknown, 'no-such-demo'));
        } finally {
            if (! $preexisting) {
                @unlink($file);
            }
        }
    }

    private function invokeDemoImage(DemoSelectionResolver $resolver, string $name): ?string
    {
        $method = new \ReflectionMethod($resolver, 'demoImage');
        $method->setAccessible(true);

        return $method->invoke($resolver, $name);
    }

    public function test_resolver_rejects_unknown_selection(): void
    {
        $resolver = app(DemoSelectionResolver::class);

        $this->assertFalse($resolver->isValid('preset:nope'));
        $this->assertSame([], $resolver->overridesFor('preset:nope'));
        $this->assertSame([], $resolver->overridesFor(null));
    }

    // ─── Setting override seam ───

    public function test_setting_override_short_circuits_get_value(): void
    {
        $original = settings('homepage_template', 'default');

        Setting::overrideForRequest(['homepage_template' => 'ai-chatbot']);
        $this->assertSame('ai-chatbot', settings('homepage_template'));

        Setting::clearOverrides();
        $this->assertSame($original, settings('homepage_template', 'default'));
    }
}
