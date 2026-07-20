<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportDepartment extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'email',
        'assigned_role_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function assignedRole(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class, 'assigned_role_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'department_id');
    }

    public function cannedResponses(): HasMany
    {
        return $this->hasMany(SupportCannedResponse::class, 'department_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
