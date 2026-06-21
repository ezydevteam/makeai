<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group'];

    /**
     * Cache prefix for settings.
     */
    private const CACHE_PREFIX = 'settings:';

    private const CACHE_TTL = null; // forever, invalidated on write

    /**
     * Legacy aliases for renamed keys (PART 02 branding normalization).
     * Canonical keys use site_* prefix. app_* is backward-compat.
     */
    private const ALIASES = [
        'app_name'          => 'site_name',
        'app_url'           => 'site_url',
        'app_logo_light'    => 'site_logo_light',
        'app_logo_dark'     => 'site_logo_dark',
        'app_tagline'       => 'site_tagline',
        'app_description'   => 'site_description',
        'app_favicon_ico'   => 'site_favicon_ico',
        'app_favicon_png'   => 'site_favicon_png',
        'app_og_image'      => 'site_og_image',
        'app_support_email' => 'site_support_email',
        'app_support_url'   => 'site_support_url',
        'app_terms_url'     => 'site_terms_url',
        'app_privacy_url'   => 'site_privacy_url',
    ];

    /**
     * Get a setting value by key, with caching.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        // Resolve legacy alias
        $resolvedKey = self::ALIASES[$key] ?? $key;

        return Cache::rememberForever(self::CACHE_PREFIX.$resolvedKey, function () use ($resolvedKey, $default) {
            $setting = static::where('key', $resolvedKey)->first();

            if (! $setting) {
                return $default;
            }

            return $setting->castValue();
        });
    }

    /**
     * Set a setting value, update cache.
     */
    public static function setValue(string $key, mixed $value, string $type = 'string', ?string $group = null): void
    {
        $storeValue = $value;

        // Encrypt if needed
        if ($type === 'encrypted' && $value !== null) {
            $storeValue = Crypt::encryptString((string) $value);
        }

        // Convert json
        if ($type === 'json' && is_array($value)) {
            $storeValue = json_encode($value);
        }

        // Convert boolean
        if ($type === 'boolean') {
            $storeValue = $value ? '1' : '0';
        }

        $attributes = [
            'value' => $storeValue,
            'type' => $type,
        ];

        if ($group !== null) {
            $attributes['group'] = $group;
        }

        static::updateOrCreate(
            ['key' => $key],
            $attributes
        );

        // Invalidate cache
        Cache::forget(self::CACHE_PREFIX.$key);
        Cache::forget('settings:all');
    }

    /**
     * Get all settings as key => value array (cached).
     */
    public static function getAllCached(): array
    {
        return Cache::rememberForever('settings:all', function () {
            return static::all()->mapWithKeys(function ($setting) {
                return [$setting->key => $setting->castValue()];
            })->toArray();
        });
    }

    /**
     * Get settings by group (alias for getByGroup).
     */
    public static function getGroup(string $group): array
    {
        return static::getByGroup($group);
    }

    /**
     * Get settings by group.
     */
    public static function getByGroup(string $group): array
    {
        return static::where('group', $group)
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => $setting->castValue()];
            })
            ->toArray();
    }

    /**
     * Update a group of settings.
     */
    public static function updateGroup(string $group, array $settings): void
    {
        foreach ($settings as $key => $value) {
            // Determine type automatically or assume string
            $type = 'string';
            if (is_bool($value)) {
                $type = 'boolean';
            }
            if (is_array($value)) {
                $type = 'json';
            }
            if (str_contains($key, 'password') || str_contains($key, 'secret') || str_contains($key, 'key')) {
                $type = 'encrypted';
            }

            static::setValue($key, $value, $type, $group);
        }
    }

    /**
     * Cast the stored value to its proper PHP type.
     */
    public function castValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            'boolean' => (bool) $this->value,
            'integer' => (int) $this->value,
            'json' => json_decode($this->value, true),
            'encrypted' => Crypt::decryptString($this->value),
            default => $this->value,
        };
    }

    /**
     * Flush all settings cache.
     */
    public static function flushCache(): void
    {
        $settings = static::pluck('key');
        foreach ($settings as $key) {
            Cache::forget(self::CACHE_PREFIX.$key);
        }
        Cache::forget('settings:all');
    }
}
