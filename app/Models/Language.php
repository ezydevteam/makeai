<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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

    /**
     * Cache key for the list of active language codes — the whitelist LocaleMiddleware
     * validates every request against. Cached for the same reason as the default code:
     * without it, every page load pays for a languages lookup.
     */
    public const ACTIVE_CODES_CACHE_KEY = 'language_active_codes';

    /**
     * Shape of a valid language code — a BCP-47 subset: a 2-3 letter primary tag plus
     * optional subtags ("en", "zh-CN", "pt-BR"). Enforced on write by
     * LanguageStoreRequest and re-checked in activeCodes() so a row written before this
     * rule existed (or by a direct DB edit) still cannot reach App::setLocale().
     *
     * This is a real availability guard, not just tidiness: Laravel's Translator throws
     * InvalidArgumentException on any locale containing "/" or "\", and LocaleMiddleware
     * runs on every request — so one malformed active code 500s the entire site.
     */
    public const CODE_PATTERN = '/^[a-zA-Z]{2,3}([-_][a-zA-Z0-9]{2,8})*$/';

    /**
     * Whether a code is safely usable as an application locale.
     */
    public static function isValidCode(?string $code): bool
    {
        return is_string($code) && $code !== '' && preg_match(self::CODE_PATTERN, $code) === 1;
    }

    protected $fillable = [
        'code', 'name', 'flag', 'is_rtl',
        'is_default', 'is_active',
        'date_format', 'time_format', 'number_system',
    ];

    protected $casts = [
        'is_rtl' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Any language save/delete can change which row is the default or which codes
        // are active, so drop both cached resolutions. Instance saves fire here; the
        // mass update in LanguageController::setDefault also forgets them explicitly.
        static::saved(fn () => self::forgetResolutionCaches());
        static::deleted(fn () => self::forgetResolutionCaches());
    }

    /**
     * Drop every cached language resolution. Call after any write that bypasses model
     * events (mass updates), otherwise a deactivated language keeps resolving.
     */
    public static function forgetResolutionCaches(): void
    {
        Cache::forget(self::DEFAULT_CODE_CACHE_KEY);
        Cache::forget(self::ACTIVE_CODES_CACHE_KEY);
    }

    /**
     * The active language codes, cached. This is the whitelist LocaleMiddleware checks a
     * requested locale against, so it must fail closed: if the DB/cache isn't ready this
     * returns an empty list, which makes the middleware fall back to config('app.locale')
     * rather than trusting an unvalidated value.
     *
     * @return array<int, string>
     */
    public static function activeCodes(): array
    {
        try {
            return Cache::rememberForever(
                self::ACTIVE_CODES_CACHE_KEY,
                fn () => static::where('is_active', true)
                    ->pluck('code')
                    // Drop malformed codes rather than trust the table: one of these
                    // reaching setLocale() throws on every request. Filtering here means
                    // a bad row degrades to "that language is unavailable" instead of a
                    // site-wide 500.
                    ->filter(fn (?string $code) => self::isValidCode($code))
                    ->values()
                    ->all()
            );
        } catch (\Throwable $e) {
            // DB/cache not ready (e.g. mid-install).
            return [];
        }
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
                function () {
                    $code = static::where('is_default', true)->value('code');

                    // A malformed default would be handed to setLocale() on every request
                    // and to the frontend as the shared locale; fall back to config.
                    return self::isValidCode($code) ? $code : config('app.locale', 'en');
                }
            );
        } catch (\Throwable $e) {
            // DB/cache not ready (e.g. mid-install) — fall back to config.
            return config('app.locale', 'en');
        }
    }

    /**
     * Get the default language.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }
}
