<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Language extends Model
{
    /**
     * Cache key for the default language code. The default language is tracked
     * ONLY by the is_default column (single source of truth); this caches the
     * resolved code so hot paths (LocaleMiddleware, Inertia share) don't hit the
     * DB on every request.
     */
    public const DEFAULT_CODE_CACHE_KEY = 'language_default_code';

    protected $fillable = [
        'code', 'name', 'flag', 'is_rtl',
        'is_default', 'is_active',
        'date_format', 'time_format', 'decimal_separator',
        'thousands_separator', 'number_system', 'currency_position',
    ];

    protected $casts = [
        'is_rtl' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Any language save/delete can change which row is the default, so drop
        // the cached code. Instance saves fire here; the mass update in
        // LanguageController::setDefault also forgets the key explicitly.
        static::saved(fn () => Cache::forget(self::DEFAULT_CODE_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::DEFAULT_CODE_CACHE_KEY));
    }

    /**
     * Resolve the default language code from the authoritative is_default
     * column (cached). Self-guarding so it is a safe drop-in for the old
     * settings('default_language') reads even before the DB/cache is ready.
     */
    public static function defaultCode(): string
    {
        try {
            return Cache::rememberForever(
                self::DEFAULT_CODE_CACHE_KEY,
                fn () => static::where('is_default', true)->value('code') ?: config('app.locale', 'en')
            );
        } catch (\Throwable $e) {
            // DB/cache not ready (e.g. mid-install) — fall back to config.
            return config('app.locale', 'en');
        }
    }

    /**
     * Get translations for this language.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }

    /**
     * Get the default language.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }
}
