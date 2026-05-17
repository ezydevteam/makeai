<?php

namespace App\Services;

use App\Models\Language;
use App\Models\Translation;
use Illuminate\Support\Facades\Cache;

class TranslationService
{
    /**
     * Get all translations for a language code.
     */
    public static function getTranslations(string $langCode): array
    {
        return Cache::rememberForever("translations_{$langCode}", function () use ($langCode) {
            $language = Language::where('code', $langCode)->first();
            if (! $language) {
                return [];
            }

            return Translation::where('language_id', $language->id)
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    /**
     * Clear translation cache.
     */
    public static function clearCache(?string $langCode = null): void
    {
        if ($langCode) {
            Cache::forget("translations_{$langCode}");
        } else {
            foreach (Language::pluck('code') as $code) {
                Cache::forget("translations_{$code}");
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
