<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Payment extends Model
{
    protected $fillable = [
        'ulid', 'user_id', 'plan_id', 'subscription_id', 'gateway',
        'gateway_payment_id', 'amount', 'currency', 'status', 'type', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $p) {
            if (empty($p->ulid)) {
                $p->ulid = (string) Str::ulid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
