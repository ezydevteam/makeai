<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin Login History — records every admin login attempt.
 */
class AdminLoginHistory extends Model
{
    protected $table = 'admin_login_history';

    protected $fillable = [
        'admin_id', 'ip', 'user_agent', 'country', 'city', 'success',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}