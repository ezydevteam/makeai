<?php

namespace App\Traits;

use App\Models\AdminPermission;
use App\Models\AdminRole;

/**
 * HasRBAC — Role-Based Access Control for Admin model.
 *
 * Provides permission checking methods against the admin's role.
 */
trait HasRBAC
{
    /** @var string[]|null Cached permission slugs for this request. */
    protected ?array $cachedPermissionSlugs = null;

    /** @var bool|null Cached super-admin status. */
    protected ?bool $cachedIsSuperAdmin = null;

    /**
     * Get the admin's role.
     */
    public function role()
    {
        return $this->belongsTo(AdminRole::class, 'role_id');
    }

    /**
     * Check if admin has a specific permission by slug.
     *
     * @example $admin->hasPermission('users.edit')
     */
    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($slug, $this->getAllPermissions(), true);
    }

    /**
     * Check if admin has ANY of the given permissions.
     *
     * @example $admin->hasAnyPermission(['users.view', 'users.edit'])
     */
    public function hasAnyPermission(array $slugs): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return ! empty(array_intersect($slugs, $this->getAllPermissions()));
    }

    /**
     * Check if admin has ALL of the given permissions.
     */
    public function hasAllPermissions(array $slugs): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return empty(array_diff($slugs, $this->getAllPermissions()));
    }

    /**
     * Check if admin is Super Admin (system role).
     */
    public function isSuperAdmin(): bool
    {
        if ($this->cachedIsSuperAdmin !== null) {
            return $this->cachedIsSuperAdmin;
        }

        if (! $this->role) {
            return $this->cachedIsSuperAdmin = false;
        }

        return $this->cachedIsSuperAdmin = ($this->role->slug === config('auth.providers.admins.super_admin_slug', 'super-admin'));
    }

    /**
     * Get all permission slugs for this admin, cached for the request lifetime.
     */
    public function getAllPermissions(): array
    {
        if ($this->cachedPermissionSlugs !== null) {
            return $this->cachedPermissionSlugs;
        }

        if (! $this->role) {
            return $this->cachedPermissionSlugs = [];
        }

        if ($this->isSuperAdmin()) {
            return $this->cachedPermissionSlugs = AdminPermission::pluck('slug')->toArray();
        }

        return $this->cachedPermissionSlugs = $this->role
            ->permissions()
            ->pluck('slug')
            ->toArray();
    }

    /**
     * Clear the permission cache. Call after role/permission changes.
     */
    public function clearPermissionCache(): void
    {
        $this->cachedPermissionSlugs = null;
        $this->cachedIsSuperAdmin = null;
    }
}
