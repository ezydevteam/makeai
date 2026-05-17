<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialFollowCount extends Model
{
    protected $fillable = ['platform', 'count', 'is_active'];

    protected $casts = [
        'count' => 'integer',
        'is_active' => 'boolean',
    ];
}
