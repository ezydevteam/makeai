<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    protected $fillable = [
        'slug', 'name', 'version', 'is_active', 'manifest',
        'installed_at', 'activated_at',
    ];

    protected $casts = [
        'manifest' => 'array',
        'is_active' => 'boolean',
        'installed_at' => 'datetime',
        'activated_at' => 'datetime',
    ];
}
