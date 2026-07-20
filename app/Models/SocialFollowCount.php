<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialFollowCount extends Model
{
    protected $fillable = [
        'platform',
        'profile_url',
        'count',
        'manual_count',
        'count_source',
        'fetch_enabled',
        'sort_order',
        'is_active',
        'last_fetched_at',
        'last_error',
    ];

    protected $casts = [
        'count' => 'integer',
        'manual_count' => 'integer',
        'fetch_enabled' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'last_fetched_at' => 'datetime',
    ];
}
