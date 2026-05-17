<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiKey extends Model
{
    protected $fillable = [
        'provider',
        'api_key',
        'label',
        'is_active',
        'last_used_at',
        'usage_count',
        'error_count',
        'disabled_until',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'disabled_until' => 'datetime',
        'usage_count' => 'integer',
        'error_count' => 'integer',
    ];

    /**
     * Encrypt the API key when setting it.
     */
    public function setApiKeyAttribute($value)
    {
        $this->attributes['api_key'] = Crypt::encryptString($value);
    }

    /**
     * Decrypt the API key when getting it.
     */
    public function getApiKeyAttribute($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value; // Fallback if not encrypted or key changed
        }
    }

    /**
     * Scope to only include active and non-disabled keys.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('disabled_until')
                    ->orWhere('disabled_until', '<=', now());
            });
    }

    /**
     * Scope to only include keys for a specific provider.
     */
    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }
}
