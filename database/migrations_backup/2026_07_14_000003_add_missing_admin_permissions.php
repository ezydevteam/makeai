<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add the admin permissions that were missing from the Roles & Permissions screen.
 *
 * Two problems:
 *
 * 1. `contact.messages` was ENFORCED by the contact routes but had no row, so
 *    hasPermission() was false for every non-super-admin: the Contact Messages page 403'd
 *    and could not be granted, because it wasn't on the screen to tick. Same for
 *    `themes.manage`, referenced by ThemeController::installTheme().
 *
 * 2. Whole features had no permission of their own and rode on an unrelated broad slug —
 *    tool reviews on `ai.tools`, announcements on `content.pages`, newsletter on
 *    `users.manage`, and ads plus every system-settings page (storage, integrations,
 *    extensions, OAuth, social counters, notifications, health, updates, maintenance,
 *    cache, rate limits) on the single `settings.manage` tick. There was no way to
 *    delegate one without handing over everything.
 *
 * The routes now accept `new.slug,legacy.slug` (the permission middleware ORs its
 * arguments), so roles that already hold the broad slug are unaffected — the new
 * permissions simply become grantable on their own.
 *
 * Idempotent.
 */
return new class extends Migration
{
    /** @var array<int, array{slug: string, name: string, group: string}> */
    private const PERMISSIONS = [
        ['slug' => 'contact.messages', 'name' => 'View Contact Messages', 'group' => 'contact'],
        ['slug' => 'contact.settings', 'name' => 'Manage Contact Settings', 'group' => 'contact'],

        ['slug' => 'marketing.ads', 'name' => 'Manage Advertisements', 'group' => 'marketing'],
        ['slug' => 'marketing.announcements', 'name' => 'Manage Announcements', 'group' => 'marketing'],
        ['slug' => 'marketing.newsletter', 'name' => 'Manage Newsletter', 'group' => 'marketing'],

        ['slug' => 'ai.reviews', 'name' => 'Moderate Tool Reviews', 'group' => 'ai'],

        ['slug' => 'themes.manage', 'name' => 'Install & Manage Themes', 'group' => 'themes'],

        ['slug' => 'settings.storage', 'name' => 'Storage Settings', 'group' => 'settings'],
        ['slug' => 'settings.integrations', 'name' => 'Integrations', 'group' => 'settings'],
        ['slug' => 'settings.extensions', 'name' => 'Extensions', 'group' => 'settings'],
        ['slug' => 'settings.oauth', 'name' => 'OAuth Settings', 'group' => 'settings'],
        ['slug' => 'settings.social', 'name' => 'Social Counters', 'group' => 'settings'],
        ['slug' => 'settings.notifications', 'name' => 'Notification Settings', 'group' => 'settings'],

        ['slug' => 'system.health', 'name' => 'View System Health & Cron', 'group' => 'system'],
        ['slug' => 'system.updates', 'name' => 'Manage Updates', 'group' => 'system'],
        ['slug' => 'system.maintenance', 'name' => 'Manage Maintenance Mode', 'group' => 'system'],
        ['slug' => 'system.cache', 'name' => 'Clear Cache', 'group' => 'system'],
        ['slug' => 'system.rate_limits', 'name' => 'Manage Rate Limits', 'group' => 'system'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('admin_permissions')) {
            return;
        }

        $ids = [];

        foreach (self::PERMISSIONS as $permission) {
            $existing = DB::table('admin_permissions')->where('slug', $permission['slug'])->value('id');

            if ($existing) {
                $ids[] = $existing;

                continue;
            }

            $ids[] = DB::table('admin_permissions')->insertGetId($permission + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->grantToSuperAdmin($ids);
    }

    public function down(): void
    {
        // Irreversible by design: dropping these rows would cascade the pivot and revoke
        // grants, and would re-break the routes that enforce contact.messages.
    }

    /**
     * The Super Admin bypasses permission checks in code, but keep its pivot complete so the
     * role renders correctly on screen. Never detach anything already granted.
     *
     * @param  int[]  $permissionIds
     */
    private function grantToSuperAdmin(array $permissionIds): void
    {
        $permissionIds = array_values(array_unique(array_filter($permissionIds)));

        if ($permissionIds === [] || ! Schema::hasTable('admin_roles') || ! Schema::hasTable('admin_role_permissions')) {
            return;
        }

        $roleId = DB::table('admin_roles')
            ->where('slug', config('auth.providers.admins.super_admin_slug', 'super-admin'))
            ->value('id');

        if (! $roleId) {
            return;
        }

        $already = DB::table('admin_role_permissions')->where('role_id', $roleId)->pluck('permission_id')->all();

        $rows = [];

        foreach (array_diff($permissionIds, $already) as $permissionId) {
            $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionId];
        }

        if ($rows !== []) {
            DB::table('admin_role_permissions')->insert($rows);
        }
    }
};
