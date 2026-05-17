<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Credit Transaction — logs every credit change (Part 4.1).
 */
class CreditTransaction extends Model
{
    protected $fillable = [
        'user_id', 'amount', 'balance_after', 'type', 'description', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'balance_after' => 'decimal:4',
            'meta' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
