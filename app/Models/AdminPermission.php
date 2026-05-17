<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdminPermission extends Model
{
    protected $fillable = ['name', 'slug', 'group', 'description'];

    /**
     * Roles that have this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminRole::class,
            'admin_role_permissions',
            'permission_id',
            'role_id'
        );
    }

    /**
     * Get all permissions grouped by group name.
     */
    public static function allGrouped(): array
    {
        return static::orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group')
            ->toArray();
    }
}
