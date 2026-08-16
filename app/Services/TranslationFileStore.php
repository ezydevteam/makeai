<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Reads and writes the per-language translation catalogues in lang/{code}.json.
 *
 * These files are the system of record for translations. They used to live only in the
 * `translations` table, which made them the one piece of user-authored content with no
 * seeder, no export and no file backing: `migrate:fresh` — which demo:reset runs on a
 * schedule — destroyed them with no way back, and a buyer's package shipped with none of
 * them at all. Files survive a database rebuild, ship inside the release, and diff in git.
 *
 * The database table is still populated, but only as a disposable index for the admin
 * screen's pagination and search. Anything it holds can be rebuilt from these files plus
 * a source scan, so losing it costs nothing.
 *
 * Only entries whose value actually differs from the key are stored. Both consumers —
 * translate() in PHP and t() in useTranslate.ts — fall back to the key when a string is
 * absent, so an identity entry carries no information, and on a mostly-untranslated
 * language that is the overwhelming majority of ~6,000 keys.
 */
class TranslationFileStore
{
    /**
     * Locale codes come from the `languages` table, which is admin-editable, so they are
     * treated as untrusted input on the way to a filesystem path.
     */
    private static function isValidLocale(string $locale): bool
    {
        return $locale !== '' && preg_match('/^[a-zA-Z0-9_-]+$/', $locale) === 1;
    }

    public static function path(string $locale): ?string
    {
        return static::isValidLocale($locale) ? lang_path("{$locale}.json") : null;
    }

    /**
     * The stored catalogue for a locale: key => translated value.
     */
    public static function get(string $locale): array
    {
        $path = static::path($locale);

        if ($path === null || ! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            // A hand-edited file with a stray comma should degrade to "no translations"
            // rather than take the whole site down — every consumer falls back to the key.
            report(new \RuntimeException("lang/{$locale}.json is not valid JSON and was ignored."));

            return [];
        }

        // Guard against nested structures: this catalogue is deliberately flat, and a
        // non-scalar value would blow up string interpolation at render time.
        //
        // Keys are cast rather than type-checked: json_decode() turns a numeric object key
        // into an int, so a legitimate source string like "0" or "2026" would be thrown
        // away by an is_string() test on the key.
        $catalogue = [];

        foreach ($decoded as $key => $value) {
            if (is_string($value)) {
                $catalogue[(string) $key] = $value;
            }
        }

        return $catalogue;
    }

    /**
     * Merge $pairs into the locale's file. Keys absent from $pairs are left alone, so a
     * paginated save touches only the rows on that page.
     *
     * Returns false when the file could not be written — the caller surfaces that rather
     * than reporting a save that silently did not happen.
     */
    public static function merge(string $locale, array $pairs): bool
    {
        $path = static::path($locale);

        if ($path === null) {
            return false;
        }

        $catalogue = static::get($locale);

        foreach ($pairs as $key => $value) {
            // Cast for the same reason as get(): a numeric source string arrives as an int.
            $key = (string) $key;

            if (! is_string($value)) {
                continue;
            }

            // An entry equal to its key, or cleared by the admin, is dropped rather than
            // stored: absence and identity mean the same thing to every consumer.
            if ($value === '' || $value === $key) {
                unset($catalogue[$key]);

                continue;
            }

            $catalogue[$key] = $value;
        }

        return static::write($locale, $catalogue);
    }

    /**
     * Replace a locale's file outright.
     */
    public static function write(string $locale, array $catalogue): bool
    {
        $path = static::path($locale);

        if ($path === null) {
            return false;
        }

        if (! File::isDirectory(dirname($path)) && ! File::makeDirectory(dirname($path), 0755, true, true)) {
            return false;
        }

        // Drop identity and empty entries here rather than only in merge(): this is the one
        // choke point every write passes through, so the invariant holds no matter who calls.
        // An entry equal to its key carries no information — getForLocale() rejects it at
        // render time anyway — and leaving it in the file makes an untranslated string look
        // translated in the admin screen and in the count.
        $catalogue = array_filter(
            $catalogue,
            fn ($value, $key): bool => is_string($value) && $value !== '' && $value !== (string) $key,
            ARRAY_FILTER_USE_BOTH
        );

        // Sorted so the file has a stable order: these are meant to be diffable in git,
        // and hash-order output would show every line as changed after any edit.
        ksort($catalogue);

        $json = json_encode(
            (object) $catalogue,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            return false;
        }

        // Written to a temporary file and renamed, so a crash or a concurrent read can
        // never observe a half-written catalogue — the failure mode there is an untranslated
        // site, and on Windows an unreadable file for every request until someone notices.
        $temp = $path . '.' . getmypid() . '.tmp';

        if (file_put_contents($temp, $json . PHP_EOL, LOCK_EX) === false) {
            return false;
        }

        // rename() will not overwrite on Windows, so the destination goes first. The
        // window between the two is why the temp file is written before either happens.
        if (is_file($path) && ! @unlink($path)) {
            @unlink($temp);

            return false;
        }

        if (! @rename($temp, $path)) {
            @unlink($temp);

            return false;
        }

        TranslationService::clearCache($locale);

        return true;
    }

    /**
     * Whether the catalogue directory can actually be written to. Surfaced by the admin
     * screen so a read-only lang/ is reported as a permissions problem, rather than as
     * edits that appear to save and are gone on the next request.
     */
    public static function isWritable(): bool
    {
        $dir = lang_path();

        if (! File::isDirectory($dir)) {
            return File::isWritable(dirname($dir));
        }

        return File::isWritable($dir);
    }

    /**
     * Every locale that currently has a catalogue file.
     *
     * @return list<string>
     */
    public static function locales(): array
    {
        if (! File::isDirectory(lang_path())) {
            return [];
        }

        $locales = [];

        foreach (File::files(lang_path()) as $file) {
            if ($file->getExtension() === 'json') {
                $locales[] = $file->getBasename('.json');
            }
        }

        sort($locales);

        return $locales;
    }
}
