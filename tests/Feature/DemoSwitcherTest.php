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
 *   - Nav menu: ?demo_hero / ?demo_tool swap a page-level layout variant only.
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

    /**
     * The chosen demo is named in the address bar, so a buyer can copy the URL of the
     * demo they are looking at. The bare name is used, not the `preset:` key, so the
     * link stays readable.
     */
    public function test_select_route_stamps_the_demo_name_into_the_redirect_url(): void
    {
        config(['demo.enabled' => true]);

        $this->from('/pricing?plan=pro')
            ->get('/__demo/select?demo=preset:midnight')
            ->assertRedirect(url('/pricing') . '?plan=pro&demo=midnight');
    }

    public function test_select_route_strips_the_demo_name_for_default(): void
    {
        config(['demo.enabled' => true]);

        // Switching back to the real site must not leave the old demo in the URL.
        $this->from(url('/pricing') . '?demo=midnight')
            ->get('/__demo/select?demo=default')
            ->assertRedirect(url('/pricing'));
    }

    // ─── Shareable ?demo= URLs ───

    public function test_demo_query_param_applies_the_demo_without_a_cookie(): void
    {
        config(['demo.enabled' => true]);

        // Someone opening a shared link has no cookie — the URL alone has to work.
        $this->get('/?demo=midnight')->assertInertia(
            fn (Assert $page) => $page->where('demoBar.active', 'preset:midnight')
        );
    }

    public function test_demo_query_param_overrides_the_cookie(): void
    {
        config(['demo.enabled' => true]);

        $this->withCookie('demo_selection', 'preset:midnight')
            ->get('/?demo=default')
            ->assertInertia(fn (Assert $page) => $page->where('demoBar.active', ''));
    }

    public function test_cookie_still_applies_when_the_url_carries_no_demo_param(): void
    {
        config(['demo.enabled' => true]);

        $this->withCookie('demo_selection', 'preset:midnight')
            ->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('demoBar.active', 'preset:midnight'));
    }

    public function test_demo_query_param_is_ignored_when_demo_disabled(): void
    {
        config(['demo.enabled' => false]);

        $this->get('/?demo=midnight')->assertInertia(fn (Assert $page) => $page->where('demoBar', null));
    }

    public function test_select_route_ignores_an_unknown_selection(): void
    {
        config(['demo.enabled' => true]);

        // Not on the catalog → treated like "default": the cookie is cleared, never set.
        $this->get('/__demo/select?demo=preset:does-not-exist')
            ->assertCookieExpired('demo_selection');
    }

    // ─── Nav menu (page-level layout only) ───

    public function test_demo_hero_query_overrides_hero_variant(): void
    {
        config(['demo.enabled' => true]);

        $this->get('/?demo_hero=hero-2')->assertInertia(
            fn (Assert $page) => $page->where('frontendHomepageSettings.hero_variant', 'tools-grid')
        );
    }

    /**
     * Every hero layout the theme renders has to be reachable from the nav, or the demo
     * quietly hides half the styles a buyer is paying for.
     */
    public function test_demo_hero_nav_covers_every_hero_layout(): void
    {
        $source = (string) file_get_contents(resource_path('themes/default/js/Sections/HeroSection.vue'));
        preg_match_all("/heroLayoutVariant === '([a-z-]+)'/", $source, $matches);

        $rendered = collect($matches[1])->unique()->sort()->values();

        $configured = collect(config('demo.nav.hero.items'))->pluck('hero_variant')->sort()->values();

        $this->assertNotEmpty($rendered, 'Could not read the hero layouts out of HeroSection.vue.');
        $this->assertSame($rendered->all(), $configured->all());
    }

    public function test_demo_hero_query_is_ignored_when_demo_disabled(): void
    {
        config(['demo.enabled' => false]);

        // With demo off the query param must not reshuffle the homepage.
        $this->get('/?demo_hero=hero-2')->assertInertia(
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
            $this->assertTrue($labels->contains('Hero'), 'Header menu should carry the demo Hero parent.');
            $this->assertTrue($labels->contains('Tool Page'), 'Header menu should carry the demo Tool Page parent.');

            // A child links through ?demo_hero so it changes only the target-page layout.
            $hero1 = collect($main['items'])->firstWhere('label', 'Hero 1 — Centered');
            $this->assertNotNull($hero1);
            $this->assertSame('demo-hero', $hero1['parent_id']);
            $this->assertStringContainsString('demo_hero=hero-1', $hero1['url']);

            // Presets belong to the selector modal, not the nav.
            $this->assertFalse(
                collect($main['items'])->contains(fn (array $i) => str_contains((string) $i['url'], '/__demo/select')),
                'Theme presets must not be listed in the demo nav.'
            );
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

    /**
     * The screenshot is found by probing the demos directory for the demo's own name, so
     * every bundled demo is expected to have one and the extension is nobody's business but
     * the file's. Pinning one (it used to assert midnight.png) meant re-shooting the cards
     * as JPEG broke a test that has nothing to do with image formats.
     */
    public function test_resolver_auto_picks_a_dropped_in_screenshot(): void
    {
        $dir = public_path('assets/image/demo-assets/demos');
        $catalog = app(DemoSelectionResolver::class)->catalog();

        $this->assertNotEmpty($catalog, 'The demo catalog should list the bundled presets.');

        foreach ($catalog as $demo) {
            $name = str_contains($demo['key'], ':') ? explode(':', $demo['key'], 2)[1] : $demo['key'];

            $onDisk = collect(['png', 'jpg', 'jpeg', 'webp'])
                ->map(fn (string $ext) => "{$name}.{$ext}")
                ->first(fn (string $file) => file_exists("{$dir}/{$file}"));

            $this->assertNotNull($onDisk, "No screenshot in demo-assets/demos for [{$name}].");
            $this->assertSame("/assets/image/demo-assets/demos/{$onDisk}", $demo['image'], "[{$name}] resolved to the wrong screenshot.");
        }

        // A demo with no screenshot dropped in reports null, so the UI falls back to its
        // icon placeholder.
        $this->assertNull($this->invokeDemoImage(app(DemoSelectionResolver::class), 'no-such-demo'));
    }

    private function invokeDemoImage(DemoSelectionResolver $resolver, string $name): ?string
    {
        $method = new \ReflectionMethod($resolver, 'demoImage');
        $method->setAccessible(true);

        return $method->invoke($resolver, $name);
    }

    public function test_resolver_normalises_a_bare_demo_name(): void
    {
        $resolver = app(DemoSelectionResolver::class);

        // The URL carries the readable half; the full key still works.
        $this->assertSame('preset:midnight', $resolver->resolveKey('midnight'));
        $this->assertSame('preset:midnight', $resolver->resolveKey('preset:midnight'));
        $this->assertSame('midnight', $resolver->shortName('preset:midnight'));

        // Everything that means "the real site" collapses to null.
        $this->assertNull($resolver->resolveKey('default'));
        $this->assertNull($resolver->resolveKey(''));
        $this->assertNull($resolver->resolveKey(null));
        $this->assertNull($resolver->resolveKey('does-not-exist'));
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
