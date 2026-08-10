<?php

namespace App\Services;

use App\Models\Language;
use App\Models\Translation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

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
        if (! Schema::hasTable('languages') || ! Schema::hasTable('translations')) {
            return [];
        }

        // v2: entries whose value equals their key are no longer stored (see below).
        // The suffix invalidates caches written by the previous format.
        $cacheKey = "makeai:translations:v2:{$locale}";
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
                // Drop identity entries ("Save" => "Save"). Both consumers — translate()
                // in PHP and t() in useTranslate.ts — already fall back to the key when a
                // entry is absent, so these carry no information. On an untranslated
                // English install that is ~5,000 of ~5,040 keys, and this catalogue is
                // serialised into the HTML of every page as an Inertia prop.
                ->reject(fn ($value, $key) => $value === $key)
                ->toArray();
        });
    }

    private static function translationsFor(string $langCode): Collection
    {
        if (! Schema::hasTable('languages') || ! Schema::hasTable('translations')) {
            return collect();
        }

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
        if (! Schema::hasTable('languages')) {
            return;
        }

        if ($langCode) {
            Cache::forget("translations_{$langCode}");
            Cache::forget("makeai:translations:{$langCode}");
            Cache::forget("makeai:translations:v2:{$langCode}");
        } else {
            foreach (Language::pluck('code') as $code) {
                Cache::forget("translations_{$code}");
                Cache::forget("makeai:translations:{$code}");
                Cache::forget("makeai:translations:v2:{$code}");
            }
        }
    }

    /**
     * Sync a translation key across all languages if missing.
     */
    public static function syncKey(string $key): void
    {
        if (! Schema::hasTable('languages') || ! Schema::hasTable('translations')) {
            return;
        }

        $languages = Language::all();
        foreach ($languages as $lang) {
            Translation::firstOrCreate(
                ['language_id' => $lang->id, 'key' => $key],
                ['value' => $key]
            );
        }
    }
}
