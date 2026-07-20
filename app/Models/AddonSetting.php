<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-addon settings, scoped by addon slug.
 *
 * Replaces the flat `addon_{slug}_{key}` rows that previously lived in the shared
 * `settings` table (they were ~42% of it). Scoping by `addon_slug` makes uninstall a
 * single `DELETE WHERE addon_slug = ?` instead of a LIKE-purge, and keeps one addon's
 * config from bloating global settings reads.
 *
 * Value storage and casting mirror {@see Setting} exactly, so a value moved across by
 * the backfill migration reads back identically (encrypted/json/boolean included).
 */
class AddonSetting extends Model
{
    protected $fillable = ['addon_slug', 'key', 'value', 'type'];

    private const CACHE_PREFIX = 'addon_settings:';

    /**
     * All settings for one addon as [key => ['value' => raw, 'type' => type]], cached
     * per slug and invalidated whenever that addon's settings change.
     */
    private static function mapForAddon(string $slug): array
    {
        return Cache::rememberForever(self::CACHE_PREFIX.$slug, function () use ($slug) {
            return static::where('addon_slug', $slug)
                ->get(['key', 'value', 'type'])
                ->mapWithKeys(fn ($row) => [$row->key => ['value' => $row->value, 'type' => $row->type]])
                ->toArray();
        });
    }

    /**
     * Read one addon setting, cast to its PHP type. Returns $default when unset.
     */
    public static function get(string $slug, string $key, mixed $default = null): mixed
    {
        $entry = self::mapForAddon($slug)[$key] ?? null;

        if ($entry === null) {
            return $default;
        }

        return self::castStored($entry['value'], $entry['type']) ?? $default;
    }

    /**
     * Whether a key has actually been persisted (distinct from get() returning a
     * caller's fallback for an unset key).
     */
    public static function isPersisted(string $slug, string $key): bool
    {
        return array_key_exists($key, self::mapForAddon($slug));
    }

    /**
     * Persist one addon setting, encoding by type exactly as Setting::setValue does.
     */
    public static function set(string $slug, string $key, mixed $value, string $type = 'string'): void
    {
        $storeValue = $value;

        if ($type === 'encrypted' && $value !== null) {
            $storeValue = Crypt::encryptString((string) $value);
        } elseif ($type === 'json' && is_array($value)) {
            $storeValue = json_encode($value);
        } elseif ($type === 'boolean') {
            $storeValue = $value ? '1' : '0';
        }

        static::updateOrCreate(
            ['addon_slug' => $slug, 'key' => $key],
            ['value' => $storeValue, 'type' => $type],
        );

        self::flush($slug);
    }

    /**
     * Delete every setting for an addon (uninstall). Atomic and cache-safe.
     */
    public static function forget(string $slug): void
    {
        static::where('addon_slug', $slug)->delete();
        self::flush($slug);
    }

    public static function flush(string $slug): void
    {
        Cache::forget(self::CACHE_PREFIX.$slug);
    }

    /**
     * Cast a stored (string) value to its PHP type — mirrors Setting::castValue().
     */
    private static function castStored(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            'encrypted' => Crypt::decryptString($value),
            default => $value,
        };
    }
}
