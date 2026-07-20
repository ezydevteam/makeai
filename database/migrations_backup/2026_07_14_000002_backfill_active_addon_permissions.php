<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create the admin permissions that active addons declare in their addon.json.
 *
 * Addon routes and admin menus gate themselves with `admin.permission:addon.*` slugs
 * (e.g. the Knowledge Base admin pages require `addon.kb.articles.manage`), but nothing
 * ever created those rows. Only ai-image-pro had them, because it shipped a bespoke
 * seeder of its own. For every other addon the slug simply did not exist, so
 * hasPermission() returned false for any non-super-admin: the addon's admin pages 403'd,
 * its menu entries were invisible, and there was no way to fix it — the permission wasn't
 * on the Roles & Permissions screen to grant.
 *
 * AddonService::syncPermissions() now runs on activation. This backfills installs where
 * the addons were activated before that existed.
 *
 * Idempotent: existing rows are left alone, so this is safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_permissions') || ! Schema::hasTable('addons')) {
            return;
        }

        $addons = DB::table('addons')->where('is_active', true)->get(['slug', 'name', 'manifest']);
        $createdIds = [];

        foreach ($addons as $addon) {
            $manifest = json_decode((string) ($addon->manifest ?? '{}'), true);
            $declared = $manifest['permissions'] ?? [];

            if (! is_array($declared)) {
                continue;
            }

            foreach ($declared as $permission) {
                $slug = $permission['slug'] ?? null;

                if (! $slug) {
                    continue;
                }

                $existing = DB::table('admin_permissions')->where('slug', $slug)->value('id');

                if ($existing) {
                    $createdIds[] = $existing;

                    continue;
                }

                $createdIds[] = DB::table('admin_permissions')->insertGetId([
                    'slug' => $slug,
                    'name' => $permission['name'] ?? $slug,
                    'group' => $permission['group'] ?? ($addon->name ?: $addon->slug),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->grantToSuperAdmin($createdIds);
    }

    public function down(): void
    {
        // Irreversible by design: deleting these rows would cascade the pivot and strip the
        // grants operators have made, to fix nothing.
    }

    /**
     * The Super Admin bypasses permission checks in code, but keep its pivot complete so the
     * role renders correctly. Never detach anything already granted.
     *
     * @param  int[]  $permissionIds
     */
    private function grantToSuperAdmin(array $permissionIds): void
    {
        $permissionIds = array_values(array_unique(array_filter($permissionIds)));

        if ($permissionIds === [] || ! Schema::hasTable('admin_roles') || ! Schema::hasTable('admin_role_permissions')) {
            return;
        }

        $superSlug = config('auth.providers.admins.super_admin_slug', 'super-admin');
        $roleId = DB::table('admin_roles')->where('slug', $superSlug)->value('id');

        if (! $roleId) {
            return;
        }

        $already = DB::table('admin_role_permissions')
            ->where('role_id', $roleId)
            ->pluck('permission_id')
            ->all();

        $rows = [];

        foreach (array_diff($permissionIds, $already) as $permissionId) {
            $rows[] = [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ];
        }

        if ($rows !== []) {
            DB::table('admin_role_permissions')->insert($rows);
        }
    }
};
