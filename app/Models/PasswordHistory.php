<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Password History — tracks previous passwords to prevent reuse.
 */
class PasswordHistory extends Model
{
    protected $table = 'password_history';

    protected $fillable = [
        'user_type', 'user_id', 'password',
    ];

    public function user(): MorphTo
    {
        return $this->morphTo();
    }
}