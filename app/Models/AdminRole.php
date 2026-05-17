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
