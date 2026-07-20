<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Login History — records every login attempt (Part 4.1).
 */
class LoginHistory extends Model
{
    protected $table = 'login_history';

    protected $fillable = [
        'user_id', 'ip', 'user_agent', 'country', 'city', 'success',
    ];

    protected $casts = [
        'success' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
