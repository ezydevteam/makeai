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
        // Super Admin bypass — has all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->role
            ->permissions()
            ->where('slug', $slug)
            ->exists();
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

        return $this->role
            ->permissions()
            ->whereIn('slug', $slugs)
            ->exists();
    }

    /**
     * Check if admin has ALL of the given permissions.
     */
    public function hasAllPermissions(array $slugs): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $count = $this->role
            ->permissions()
            ->whereIn('slug', $slugs)
            ->count();

        return $count === count($slugs);
    }

    /**
     * Check if admin is Super Admin (system role).
     */
    public function isSuperAdmin(): bool
    {
        return $this->role && $this->role->slug === 'super-admin';
    }

    /**
     * Get all permission slugs for this admin.
     */
    public function getAllPermissions(): array
    {
        if ($this->isSuperAdmin()) {
            return AdminPermission::pluck('slug')->toArray();
        }

        return $this->role
            ->permissions()
            ->pluck('slug')
            ->toArray();
    }
}
