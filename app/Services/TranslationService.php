<?php

namespace App\Services;

use App\Models\Language;
use App\Models\Translation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TranslationService
{
    /**
     * Get all translations for a language code.
     */
    public static function getTranslations(string $langCode): array
    {
        return static::getForLocale($langCode);
    }

    public static function getForLocale(string $locale): array
    {
        $cacheKey = "makeai:translations:{$locale}";
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        if ($cached !== null) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addDay(), function () use ($locale) {
            $defaultCode = Language::where('is_default', true)->value('code') ?: 'en';
            $default = static::translationsFor($defaultCode);
            $current = $locale === $defaultCode ? collect() : static::translationsFor($locale);

            return $default
                ->merge($current->filter(fn ($value) => filled($value)))
                ->toArray();
        });
    }

    private static function translationsFor(string $langCode): Collection
    {
        $cacheKey = "translations_{$langCode}";
        $cached = Cache::get($cacheKey);

        if ($cached instanceof Collection) {
            return $cached;
        }

        if (is_array($cached)) {
            return collect($cached);
        }

        if ($cached !== null) {
            Cache::forget($cacheKey);
        }

        $language = Language::where('code', $langCode)->first();
        if (! $language) {
            Cache::put($cacheKey, [], now()->addDay());

            return collect();
        }

        $translations = Translation::where('language_id', $language->id)
            ->pluck('value', 'key')
            ->toArray();

        Cache::put($cacheKey, $translations, now()->addDay());

        return collect($translations);
    }

    /**
     * Clear translation cache.
     */
    public static function clearCache(?string $langCode = null): void
    {
        if ($langCode) {
            Cache::forget("translations_{$langCode}");
            Cache::forget("makeai:translations:{$langCode}");
        } else {
            foreach (Language::pluck('code') as $code) {
                Cache::forget("translations_{$code}");
                Cache::forget("makeai:translations:{$code}");
            }
        }
    }

    /**
     * Sync a translation key across all languages if missing.
     */
    public static function syncKey(string $key): void
    {
        $languages = Language::all();
        foreach ($languages as $lang) {
            Translation::firstOrCreate(
                ['language_id' => $lang->id, 'key' => $key],
                ['value' => $key]
            );
        }
    }
}
