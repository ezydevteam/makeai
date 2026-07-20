<?php

namespace Tests\Feature;

use App\Models\AdminPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Guard against permission drift.
 *
 * Routes gate themselves with `admin.permission:<slug>`, but nothing checked that the slug
 * actually exists as a row. When it doesn't, hasPermission() returns false for every
 * non-super-admin — the page 403s and the permission can't even be granted, because it
 * isn't on the Roles & Permissions screen to tick. That is exactly how `contact.messages`
 * and every addon permission (addon.kb.*, addon.chatbot.*) ended up broken.
 *
 * Super Admins bypass permission checks entirely, which is why this class of bug survives
 * manual testing.
 */
class AdminPermissionCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every permission slug enforced by a route must exist in admin_permissions.
     *
     * @return string[]
     */
    private function routeEnforcedSlugs(): array
    {
        $slugs = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware)) {
                    continue;
                }

                if (preg_match('/(?:admin\.permission|AdminPermission):(.+)$/i', $middleware, $matches)) {
                    foreach (explode(',', $matches[1]) as $slug) {
                        $slug = trim($slug);

                        if ($slug !== '') {
                            $slugs[$slug] = true;
                        }
                    }
                }
            }
        }

        return array_keys($slugs);
    }

    public function test_every_route_enforced_permission_exists(): void
    {
        $this->seed(\Database\Seeders\AdminSeeder::class);

        $enforced = $this->routeEnforcedSlugs();
        $this->assertNotEmpty($enforced, 'Sanity: routes should enforce some permissions.');

        $existing = AdminPermission::pluck('slug')->all();

        // Addon permissions are created on activation (AddonService::syncPermissions), so an
        // inactive addon's slug legitimately has no row yet. Core slugs must always exist.
        $core = array_values(array_filter(
            $enforced,
            static fn (string $slug): bool => ! str_starts_with($slug, 'addon.'),
        ));

        $missing = array_values(array_diff($core, $existing));

        $this->assertSame(
            [],
            $missing,
            "These permissions are enforced by routes but have no row, so they can never be granted:\n  - "
                .implode("\n  - ", $missing),
        );
    }

    public function test_the_features_that_were_missing_now_have_permissions(): void
    {
        $this->seed(\Database\Seeders\AdminSeeder::class);

        $expected = [
            'contact.messages',        // was enforced with no row — hard 403
            'contact.settings',
            'ai.reviews',              // tool reviews
            'marketing.ads',           // advertisement
            'marketing.announcements', // announcements
            'marketing.newsletter',    // newsletter
            'themes.manage',           // referenced by ThemeController, had no row
            'settings.storage',
            'settings.integrations',
            'settings.extensions',
            'settings.oauth',
            'settings.social',
            'settings.notifications',
            'system.health',
            'system.updates',
            'system.maintenance',
            'system.cache',
            'system.rate_limits',
        ];

        $existing = AdminPermission::pluck('slug')->all();

        foreach ($expected as $slug) {
            $this->assertContains($slug, $existing, "Missing permission: {$slug}");
        }

        // Mail already had a permission — it was never actually missing.
        $this->assertContains('settings.mail', $existing);
    }

    public function test_new_slugs_are_additive_so_legacy_roles_keep_access(): void
    {
        // Each re-gated route must still accept the broad legacy slug, or upgrading would
        // silently revoke access from every role that holds it today.
        $legacyStillAccepted = [
            'admin.ads.index' => 'settings.manage',
            'admin.announcements.index' => 'content.pages',
            'admin.newsletter.index' => 'users.manage',
            'admin.ai.reviews.index' => 'ai.tools',
            'admin.storage.settings' => 'settings.manage',
            'admin.system.health' => 'settings.manage',
        ];

        foreach ($legacyStillAccepted as $routeName => $legacySlug) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Route {$routeName} not found.");

            $middleware = implode(',', $route->gatherMiddleware());

            $this->assertStringContainsString(
                $legacySlug,
                $middleware,
                "Route {$routeName} must still accept the legacy '{$legacySlug}' permission.",
            );
        }
    }
}
