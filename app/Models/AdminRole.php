<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminRole extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_system'];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    protected static function booted(): void
    {
        $superSlug = config('auth.providers.admins.super_admin_slug', 'super-admin');

        // Prevent setting is_system=true on any non-super-admin role via raw DB access
        static::creating(function (AdminRole $role) use ($superSlug) {
            if ($role->is_system && $role->slug !== $superSlug) {
                $role->is_system = false;
            }
        });

        static::updating(function (AdminRole $role) use ($superSlug) {
            $originalSlug = $role->getOriginal('slug');

            // Block changing the super-admin slug to something else
            if ($originalSlug === $superSlug && $role->slug !== $superSlug) {
                $role->slug = $superSlug;
            }

            // Block changing a non-super-admin slug to super-admin
            if ($originalSlug !== $superSlug && $role->slug === $superSlug) {
                $role->slug = $originalSlug;
            }

            // Block setting is_system on non-super-admin roles
            if ($role->is_system && $role->slug !== $superSlug) {
                $role->is_system = false;
            }
        });

        // Prevent deleting the super-admin role
        static::deleting(function (AdminRole $role) use ($superSlug) {
            if ($role->slug === $superSlug) {
                return false;
            }
        });
    }

    /**
     * Permissions assigned to this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminPermission::class,
            'admin_role_permissions',
            'role_id',
            'permission_id'
        );
    }

    /**
     * Admins with this role.
     */
    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class, 'role_id');
    }

    /**
     * Sync permissions by slugs.
     */
    public function syncPermissionsBySlug(array $slugs): void
    {
        $ids = AdminPermission::whereIn('slug', $slugs)->pluck('id');
        $this->permissions()->sync($ids);
    }

    /**
     * Check if this role has a specific permission.
     */
    public function hasPermission(string $slug): bool
    {
        return $this->permissions()->where('slug', $slug)->exists();
    }
}
