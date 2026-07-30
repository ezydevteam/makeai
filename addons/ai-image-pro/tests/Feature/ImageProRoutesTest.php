<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Tests\Feature;

use Addons\AiImagePro\Models\AipAsset;
use Addons\AiImagePro\Models\AipJob;
use Addons\AiImagePro\Services\OperationRegistry;
use App\Models\Addon;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * End-to-end smoke tests: the pages render, the gates actually bite, and one
 * user cannot touch another user's images.
 */
class ImageProRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Addon::updateOrCreate(['slug' => 'ai-image-pro'], [
            'name' => 'AI Image Pro',
            'version' => '1.0.0',
            'is_active' => true,
            'manifest' => json_decode(File::get(base_path('addons/ai-image-pro/addon.json')), true),
        ]);

        $this->artisan('migrate', ['--path' => 'addons/ai-image-pro/database/migrations']);

        require_once base_path('addons/ai-image-pro/AddonServiceProvider.php');
        $this->app->register(\Addons\AiImagePro\AddonServiceProvider::class);

        // The router builds its name lookup in an app->booted() callback, which has
        // already fired by the time we register the provider here.
        $this->app['router']->getRoutes()->refreshNameLookups();
        $this->app['router']->getRoutes()->refreshActionLookups();

        // `web` appends LicenseMiddleware; an unverified test install would 403.
        settings_set('license_status', 'valid', 'string', 'license');
        Cache::forget('license.status');

        addon_setting_set('ai-image-pro', 'enabled', true, 'boolean');
        Setting::flushCache();

        // The routes' `throttle:*` limiters are IP-keyed and counted in the CACHE, which
        // RefreshDatabase does not roll back — and the array cache store lives for the
        // whole (single-process) suite, so counters bleed across test classes and 429 the
        // later cases. Throttling is orthogonal to what's under test here; the addon
        // enforces its own daily limits separately.
        //
        // Note `throttle` is aliased to the app's OWN ThrottleAiRequests (bootstrap/app.php),
        // not Illuminate's ThrottleRequests — disabling the framework class alone does nothing.
        $this->withoutMiddleware(\App\Http\Middleware\ThrottleAiRequests::class);
        Cache::flush();
    }

    private function user(): User
    {
        return User::factory()->create(['is_active' => true, 'credits' => 500]);
    }

    /** Authenticate on the `admin` guard, which the admin routes sit behind. */
    private function actingAsAdmin(): \App\Models\Admin
    {
        $role = \App\Models\AdminRole::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'is_system' => true],
        );

        config(['auth.providers.admins.super_admin_slug' => 'super-admin']);

        $admin = \App\Models\Admin::create([
            'name' => 'Super Admin',
            'email' => 'admin+' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $admin->load('role');

        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function asset(User $owner): AipAsset
    {
        return AipAsset::create([
            'user_id' => $owner->id,
            'source' => 'generated',
            'disk' => 'public',
            'path' => 'ai-image-pro/' . $owner->id . '/x.png',
            'mime' => 'image/png',
            'width' => 512,
            'height' => 512,
            'bytes' => 1024,
        ]);
    }

    public function test_the_studio_renders_for_a_logged_in_user(): void
    {
        addon_setting_set('ai-image-pro', 'studio_access', 'login');
        Setting::flushCache();

        $this->actingAs($this->user())
            ->get(route('addon.aip.user.studio'))
            ->assertOk();
    }

    public function test_the_studio_carries_the_operation_catalogue_to_the_frontend(): void
    {
        addon_setting_set('ai-image-pro', 'studio_access', 'login');
        Setting::flushCache();

        $response = $this->actingAs($this->user())->get(route('addon.aip.user.studio'));

        $response->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertNotEmpty($props['operations']);
        $this->assertArrayHasKey('key', $props['operations'][0]);
        $this->assertArrayHasKey('credits', $props['operations'][0]);
        $this->assertArrayHasKey('locked', $props['operations'][0]);
    }

    public function test_recent_assets_reach_the_studio_as_a_plain_iterable_array(): void
    {
        // Regression: AssetResource::collection() is Responsable, and Inertia resolves
        // Responsable props through toResponse() — which applies the JsonResource `data`
        // wrapper. The page got {data: [...]} instead of [...] and died on
        // `[...props.recentAssets]` with "recentAssets is not iterable".
        addon_setting_set('ai-image-pro', 'studio_access', 'login');
        Setting::flushCache();

        $owner = $this->user();
        $this->asset($owner);

        $props = $this->actingAs($owner)
            ->get(route('addon.aip.user.studio'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertIsList($props['recentAssets']);
        $this->assertArrayNotHasKey('data', $props['recentAssets']);
        $this->assertArrayHasKey('ulid', $props['recentAssets'][0]);
    }

    public function test_the_library_paginator_keeps_the_shape_the_pagination_component_reads(): void
    {
        // Regression: handing the paginator to AssetResource::collection() reshapes it to
        // {data, links:{first,last,prev,next}, meta:{…}} — `links` becomes an OBJECT and the
        // totals move under `meta`. Library.vue reads `assets.links.length` and `assets.total`,
        // so pagination silently vanished and the counts read undefined.
        $owner = $this->user();
        $this->asset($owner);

        $prop = $this->actingAs($owner)
            ->get(route('addon.aip.user.library'))
            ->assertOk()
            ->viewData('page')['props']['assets'];

        // Assert on the ENCODED shape — that is what the page actually receives. In the
        // raw prop the items are still AssetResource objects; they flatten on encode.
        $assets = json_decode(json_encode($prop), true);

        $this->assertIsList($assets['links']);           // array, not {first,last,…}
        $this->assertArrayHasKey('total', $assets);      // top level, not nested in meta
        $this->assertArrayHasKey('current_page', $assets);
        $this->assertArrayNotHasKey('meta', $assets);
        $this->assertArrayHasKey('ulid', $assets['data'][0]);
    }

    public function test_the_studio_is_closed_to_guests_when_the_admin_requires_login(): void
    {
        addon_setting_set('ai-image-pro', 'studio_access', 'login');
        Setting::flushCache();

        $response = $this->get(route('addon.aip.user.studio'));

        $this->assertContains($response->status(), [401, 403, 302]);
    }

    public function test_the_studio_opens_to_guests_when_the_admin_allows_it(): void
    {
        addon_setting_set('ai-image-pro', 'studio_access', 'guest');
        Setting::flushCache();

        $this->get(route('addon.aip.user.studio'))->assertOk();
    }

    public function test_the_studio_ships_open_to_guests_out_of_the_box(): void
    {
        // The public landing funnel: a guest must be able to reach /ai-image with no
        // settings touched at all. Uses the shipped default, deliberately not a set-up value.
        Setting::flushCache();

        // assertInertia()->component() is avoided deliberately: it resolves the page file
        // on disk and addon pages live outside resources/js/Pages, so it always misses.
        $page = $this->get(route('addon.aip.user.studio'))
            ->assertOk()
            ->viewData('page');

        $this->assertSame('Addons/ai-image-pro/User/Studio', $page['component']);
        $this->assertTrue($page['props']['isGuest']);
    }

    public function test_a_guest_may_generate_and_use_the_free_tools_but_not_the_paid_edits(): void
    {
        // The shipped funnel: the hook (generate) and the zero-cost local tools are open to
        // anonymous visitors; every paid provider edit stays behind login so guest traffic
        // cannot spend third-party API credit.
        $registry = app(\Addons\AiImagePro\Services\OperationRegistry::class);

        $this->assertSame('guest', $registry->accessLevel('generate'));

        foreach (['resize', 'crop', 'compress', 'convert'] as $freeTool) {
            $this->assertSame('guest', $registry->accessLevel($freeTool), $freeTool . ' should be open to guests');
            $this->assertSame(OperationRegistry::BILLING_FREE, $registry->billing($freeTool));
        }

        foreach (['upscale', 'bg_remove', 'inpaint', 'style_transfer'] as $paidOp) {
            $this->assertSame('login', $registry->accessLevel($paidOp), $paidOp . ' must not be open to guests');
        }
    }

    public function test_a_guest_is_cut_off_once_the_free_daily_allowance_is_spent(): void
    {
        // This 429 is what raises the signup wall on the page.
        addon_setting_set('ai-image-pro', 'guest_daily_limit', 2, 'integer');
        Setting::flushCache();

        foreach (range(1, 2) as $i) {
            AipJob::create([
                'user_id' => null,
                'guest_ip' => '127.0.0.1',
                'operation' => 'generate',
                'tier' => OperationRegistry::TIER_GENERATE,
                'engine' => 'model',
                'status' => AipJob::STATUS_COMPLETED,
            ]);
        }

        $this->postJson(route('addon.aip.user.generate'), ['prompt' => 'a red fox'])
            ->assertStatus(429);
    }

    public function test_the_public_landing_page_renders_for_a_guest_with_complete_content(): void
    {
        // A fresh install must ship a finished-looking page — not a screen of blank
        // sections waiting for the operator to write copy.
        $props = $this->get(route('addon.aip.user.landing'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertNotSame('', $props['content']['heroHeading']);
        $this->assertNotSame('', $props['content']['ctaHeading']);
        $this->assertNotEmpty($props['examples']);
        $this->assertNotEmpty($props['features']);
        $this->assertNotEmpty($props['usecases']);
        $this->assertNotEmpty($props['benefits']);
        $this->assertNotEmpty($props['steps']);
        $this->assertNotSame('', $props['seo']['title']);
        $this->assertTrue($props['isGuest']);
    }

    public function test_blank_repeater_fields_reach_the_frontend_as_strings_not_null(): void
    {
        // Laravel's ConvertEmptyStringsToNull rewrites every blank field of the admin's
        // settings form to NULL before it is stored. Vue's withDefaults only substitutes
        // for `undefined`, never `null` — so a null image URL sailed through to
        // `props.image.trim()` and took the whole landing page down with a white screen.
        addon_setting_set('ai-image-pro', 'landing_examples', [
            ['title' => 'Has a title', 'description' => null, 'image' => null, 'prompt' => 'p'],
        ], 'json');
        Setting::flushCache();

        $examples = $this->get(route('addon.aip.user.landing'))
            ->assertOk()
            ->viewData('page')['props']['examples'];

        $this->assertSame('', $examples[0]['image']);
        $this->assertSame('', $examples[0]['description']);

        foreach ($examples[0] as $field => $value) {
            $this->assertIsString($value, "example.{$field} must reach the page as a string");
        }
    }

    public function test_the_landing_page_carries_the_layout_options_and_the_full_toolset(): void
    {
        addon_setting_set('ai-image-pro', 'landing_page_width', 'boxed');
        addon_setting_set('ai-image-pro', 'landing_gradient_enabled', true, 'boolean');
        Setting::flushCache();

        $props = $this->get(route('addon.aip.user.landing'))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('boxed', $props['content']['pageWidth']);
        $this->assertTrue($props['content']['gradient']);

        // The prompt panel shows the whole toolkit and the style presets, so a visitor
        // sees what they get before signing up. Both come from the registry / presets
        // table — never a hardcoded list.
        $this->assertNotEmpty($props['operations']);
        $this->assertArrayHasKey('billing', $props['operations'][0]);
        $this->assertArrayHasKey('presets', $props);
    }

    public function test_an_invalid_page_width_falls_back_instead_of_emitting_a_broken_class(): void
    {
        addon_setting_set('ai-image-pro', 'landing_page_width', 'not-a-width');
        Setting::flushCache();

        $props = $this->get(route('addon.aip.user.landing'))->assertOk()->viewData('page')['props'];

        $this->assertSame('default', $props['content']['pageWidth']);
    }

    public function test_admin_copy_overrides_the_shipped_landing_content(): void
    {
        addon_setting_set('ai-image-pro', 'landing_hero_heading', 'Our Custom Headline');
        addon_setting_set('ai-image-pro', 'landing_examples', [
            ['title' => 'Only one', 'description' => 'd', 'image' => '', 'prompt' => 'p'],
        ], 'json');
        Setting::flushCache();

        $props = $this->get(route('addon.aip.user.landing'))->assertOk()->viewData('page')['props'];

        $this->assertSame('Our Custom Headline', $props['content']['heroHeading']);
        $this->assertCount(1, $props['examples']);
    }

    public function test_a_signed_in_user_is_sent_straight_to_the_studio(): void
    {
        // A paying user does not want to read a sales page every time they open the tool.
        $this->actingAs($this->user())
            ->get(route('addon.aip.user.landing'))
            ->assertRedirect(route('addon.aip.user.studio'));

        // ...unless they are an admin checking their landing copy.
        $this->actingAs($this->user())
            ->get(route('addon.aip.user.landing', ['preview' => 1]))
            ->assertOk();
    }

    public function test_the_landing_prompt_is_carried_into_the_studio_and_the_model_is_validated(): void
    {
        $props = $this->get(route('addon.aip.user.studio', [
            'prompt' => 'a red fox in the snow',
            'model' => 'not-a-real-model',
            'aspect' => '16:9',
        ]))->assertOk()->viewData('page')['props'];

        $this->assertSame('a red fox in the snow', $props['autoPrompt']);
        $this->assertSame('16:9', $props['autoAspect']);

        // A hand-typed query string must not be able to select a model the admin
        // never enabled — it is validated against the catalogue, not trusted.
        $this->assertNull($props['autoModel']);
    }

    public function test_every_route_uses_the_apps_three_argument_throttle_signature(): void
    {
        // `throttle` is aliased to App\Http\Middleware\ThrottleAiRequests, NOT Laravel's.
        // Its signature is {category},{max},{windowSeconds}. Laravel's familiar
        // `throttle:30,1` parses here as category "30" with a max of ONE attempt, so the
        // user is 429'd on their first click. Assert the shape, since the mistake is
        // silent — the route still registers and only fails at runtime.
        $router = app('router');

        $throttles = collect($router->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'addon.aip.'))
            ->flatMap(fn ($route) => $route->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'throttle:'))
            ->unique()
            ->values();

        $this->assertNotEmpty($throttles, 'Expected the addon to rate-limit its write routes.');

        foreach ($throttles as $throttle) {
            $args = explode(',', substr($throttle, strlen('throttle:')));

            $this->assertCount(3, $args, "[{$throttle}] must be throttle:{category},{max},{seconds}");
            $this->assertFalse(
                is_numeric($args[0]),
                "[{$throttle}] first argument is the CATEGORY, not a number — a numeric "
                . 'first arg means the max attempts silently becomes 1.',
            );
            $this->assertGreaterThan(1, (int) $args[1], "[{$throttle}] max attempts must exceed 1.");
        }
    }

    public function test_the_library_renders_for_a_logged_in_user(): void
    {
        $this->actingAs($this->user())
            ->get(route('addon.aip.user.library'))
            ->assertOk();
    }

    public function test_a_user_cannot_view_another_users_image(): void
    {
        $asset = $this->asset($this->user());

        $this->actingAs($this->user()) // a different user
            ->getJson(route('addon.aip.user.assets.show', $asset->ulid))
            ->assertForbidden();
    }

    public function test_a_user_cannot_delete_another_users_image(): void
    {
        $asset = $this->asset($this->user());

        $this->actingAs($this->user())
            ->deleteJson(route('addon.aip.user.assets.destroy', $asset->ulid))
            ->assertForbidden();

        $this->assertNotSoftDeleted('aip_assets', ['id' => $asset->id]);
    }

    public function test_a_single_asset_json_response_is_not_wrapped_in_a_data_envelope(): void
    {
        // AssetResource::$wrap = null must hold for the JSON surfaces too — the poller and
        // the synchronous tool endpoint read `response.data.asset.ulid`, not `.asset.data.ulid`.
        $owner = $this->user();
        $asset = $this->asset($owner);

        $this->actingAs($owner)
            ->getJson(route('addon.aip.user.assets.show', $asset->ulid))
            ->assertOk()
            ->assertJsonPath('asset.ulid', $asset->ulid)
            ->assertJsonMissingPath('asset.data');
    }

    public function test_an_owner_can_favorite_their_own_image(): void
    {
        $owner = $this->user();
        $asset = $this->asset($owner);

        $this->actingAs($owner)
            ->postJson(route('addon.aip.user.assets.favorite', $asset->ulid))
            ->assertOk();

        $this->assertTrue($asset->fresh()->is_favorite);
    }

    public function test_an_unknown_operation_is_rejected(): void
    {
        // An operation the registry doesn't define doesn't exist — 404, not a
        // permission error and certainly not a silent pass-through.
        $this->actingAs($this->user())
            ->postJson(route('addon.aip.user.tools', 'definitely_not_an_operation'), [])
            ->assertNotFound();
    }

    public function test_an_operation_whose_engine_has_no_api_key_is_refused(): void
    {
        addon_setting_set('ai-image-pro', 'replicate_api_key', '', 'encrypted');
        addon_setting_set('ai-image-pro', 'operations', ['upscale' => ['provider' => 'replicate']], 'json');
        Setting::flushCache();

        $owner = $this->user();
        $asset = $this->asset($owner);

        // 503: configured-but-unavailable, not a 404 or a silent success.
        $this->actingAs($owner)
            ->postJson(route('addon.aip.user.ops', 'upscale'), ['asset_ulid' => $asset->ulid])
            ->assertStatus(503);
    }

    public function test_an_admin_can_upload_a_landing_image_and_gets_a_url_back(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->actingAsAdmin();

        $response = $this->postJson(route('addon.aip.admin.media.store'), [
            'image' => \Illuminate\Http\UploadedFile::fake()->image('hero.png', 800, 600),
        ]);

        $response->assertOk()->assertJsonStructure(['success', 'url', 'path']);

        $path = $response->json('path');
        $this->assertStringStartsWith('ai-image-pro/landing/', $path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($path);
    }

    public function test_the_landing_uploader_rejects_a_file_that_is_not_an_allowed_image(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->actingAsAdmin();

        // SVG is deliberately excluded — it can carry script.
        $this->postJson(route('addon.aip.admin.media.store'), [
            'image' => \Illuminate\Http\UploadedFile::fake()->create('payload.svg', 8, 'image/svg+xml'),
        ])->assertStatus(422);
    }

    public function test_removing_an_uploaded_landing_image_by_url_reclaims_the_file(): void
    {
        // The admin UI only remembers path-for-url within one page load, so after a
        // save-and-reload a removal must still be able to delete the file — it sends the
        // stored URL and the server resolves it back to a path.
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->actingAsAdmin();

        $upload = $this->postJson(route('addon.aip.admin.media.store'), [
            'image' => \Illuminate\Http\UploadedFile::fake()->image('hero.png'),
        ])->assertOk();

        $path = $upload->json('path');
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($path);

        $this->deleteJson(route('addon.aip.admin.media.destroy'), ['url' => $upload->json('url')])
            ->assertOk();

        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($path);
    }

    public function test_the_media_remover_cannot_reach_outside_the_addons_own_directory(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('avatars/someone.png', 'x');

        $this->actingAsAdmin();

        // An external URL, a foreign path, and a traversal attempt must all be no-ops.
        foreach ([
            ['url' => 'https://cdn.example.com/marketing/hero.png'],
            ['path' => 'avatars/someone.png'],
            ['path' => 'ai-image-pro/landing/../../avatars/someone.png'],
        ] as $payload) {
            $this->deleteJson(route('addon.aip.admin.media.destroy'), $payload)->assertOk();
        }

        \Illuminate\Support\Facades\Storage::disk('public')->assertExists('avatars/someone.png');
    }

    public function test_a_guest_cannot_upload_a_landing_image(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $response = $this->postJson(route('addon.aip.admin.media.store'), [
            'image' => \Illuminate\Http\UploadedFile::fake()->image('hero.png'),
        ]);

        $this->assertContains($response->status(), [401, 403, 302]);
    }

    public function test_admin_pages_render_while_authenticated_on_the_admin_guard(): void
    {
        // Regression: the `imagePro` Inertia share runs on EVERY response, admin panel
        // included. `auth:admin` makes `admin` the default guard, so auth()->user()
        // returned an Admin there and blew up ImageAccessService's ?User type hint.
        $this->actingAsAdmin();

        $this->get(route('addon.aip.admin.settings'))->assertOk();
        $this->get(route('addon.aip.admin.overview'))->assertOk();
    }

    public function test_the_addon_is_unreachable_once_the_admin_disables_it(): void
    {
        addon_setting_set('ai-image-pro', 'enabled', false, 'boolean');
        Setting::flushCache();

        $response = $this->actingAs($this->user())->get(route('addon.aip.user.studio'));

        $this->assertContains($response->status(), [403, 404, 302]);
    }
}
