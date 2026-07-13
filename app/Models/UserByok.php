<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * BYOK (bring-your-own-key) provider credentials a user adds to bypass platform
 * credits. Distinct from the platform's own provider keys (App\Models\AiKey).
 */
class UserByok extends Model
{
    protected $table = 'user_byok';

    public $timestamps = true;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'provider', 'api_key', 'is_active',
    ];

    protected $appends = [
        'masked_api_key',
    ];

    /**
     * Hide the raw (decrypting) api_key from JSON/array serialization — only the
     * appended masked_api_key should ever reach the frontend. Without this the
     * decrypted key would be exposed anywhere the model is serialized.
     */
    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function setApiKeyAttribute(string $value): void
    {
        $this->attributes['api_key'] = Crypt::encryptString($value);
    }

    public function getApiKeyAttribute(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get a masked version of the API key for safe frontend display.
     */
    public function getMaskedApiKeyAttribute(): ?string
    {
        $rawKey = $this->api_key;

        if (! $rawKey) {
            return null;
        }

        $length = strlen($rawKey);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        $prefix = substr($rawKey, 0, 4);
        $suffix = substr($rawKey, -4);

        return $prefix . str_repeat('*', $length - 8) . $suffix;
    }
}
