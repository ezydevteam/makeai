<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code', 'symbol', 'name', 'exchange_rate',
        'decimal_places', 'is_default', 'is_active',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:6',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the default currency.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }

    /**
     * Get all active currencies.
     */
    public static function getActive()
    {
        return static::where('is_active', true)->orderBy('code')->get();
    }
}
