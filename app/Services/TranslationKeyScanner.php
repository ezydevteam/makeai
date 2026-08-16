<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Finds every translatable string the product contains.
 *
 * This exists as one class because there used to be two copies of the logic — the admin
 * translation screen and the `translations:sync` command — and they drifted. `resources/themes`
 * was added to neither for a long time (≈1,000 storefront strings unreachable), then to only
 * one of them. A single scanner means a path can only be missing from both or from neither.
 *
 * Two kinds of string are collected:
 *
 *  1. Calls in source files — translate('…') in PHP, t('…') / $t('…') in Vue and TS.
 *  2. Labels inside addon manifests. Those are plain JSON data, not code, so no regex over
 *     source files can ever see them — which is why addon menu items sat untranslatable in
 *     the admin sidebar even after addons/ was added to the file scan.
 */
class TranslationKeyScanner
{
    /**
     * Directories scanned for translatable calls.
     *
     * @return list<string>
     */
    public static function paths(): array
    {
        return [
            app_path(),
            // The admin panel and shared components.
            resource_path('js'),
            resource_path('views'),
            // The active theme: the whole public-facing site — hero, tool pages, pricing,
            // checkout. Not under resources/js, and missing here for a long time.
            resource_path('themes'),
            // Installed addons: the features a buyer pays extra for, so exactly the ones that
            // should not be stuck in English. Absent on a core-only install.
            base_path('addons'),
        ];
    }

    /**
     * Every translatable string, deduplicated.
     *
     * @return list<string>
     */
    public static function scan(): array
    {
        $keys = array_merge(static::scanSourceFiles(), static::scanAddonManifests(), static::scanThemePresets());

        $keys = array_filter($keys, static fn (string $key): bool => $key !== '' && static::isTranslatable($key));

        return array_values(array_unique($keys));
    }

    /**
     * Strings a source file passes to a translation helper.
     *
     * @return list<string>
     */
    private static function scanSourceFiles(): array
    {
        $patterns = [
            '/translate\(\s*\'([^\']*(?:\\\\.[^\']*)*)\'/',
            '/translate\(\s*"([^"]*(?:\\\\.[^"]*)*)"/',
            '/\$t\(\s*\'([^\']*(?:\\\\.[^\']*)*)\'/',
            '/\$t\(\s*"([^"]*(?:\\\\.[^"]*)*)"/',
            '/(?<![A-Za-z0-9_])t\(\s*\'([^\']*(?:\\\\.[^\']*)*)\'/',
            '/(?<![A-Za-z0-9_])t\(\s*"([^"]*(?:\\\\.[^"]*)*)"/',
        ];

        $keys = [];

        foreach (static::paths() as $path) {
            if (! File::exists($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if (! in_array($file->getExtension(), ['php', 'vue', 'ts', 'js'], true)) {
                    continue;
                }

                // Addons arrive as buyer-uploaded zips, so one may carry a bundled
                // dependency tree. Nothing translatable lives there, and walking it
                // would dwarf the rest of the scan.
                if (preg_match('#[/\\\\](node_modules|vendor|dist)[/\\\\]#', $file->getPathname())) {
                    continue;
                }

                $contents = File::get($file->getPathname());

                foreach ($patterns as $pattern) {
                    preg_match_all($pattern, $contents, $matches);

                    foreach ($matches[1] ?? [] as $match) {
                        $keys[] = trim(stripslashes($match));
                    }
                }
            }
        }

        return $keys;
    }

    /**
     * User-visible labels declared in addons/*​/addon.json.
     *
     * These render in the admin sidebar (menu labels, addon name), the addon manager
     * (description), the addon settings screen (setting labels) and the roles screen
     * (permission names), so they are as user-facing as anything in a Vue file.
     *
     * @return list<string>
     */
    private static function scanAddonManifests(): array
    {
        $keys = [];

        foreach (glob(base_path('addons/*/addon.json')) ?: [] as $manifestPath) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);

            if (! is_array($manifest)) {
                continue;
            }

            // `name` is deliberately NOT collected: it is the addon's product name —
            // "AI Assistant", "AI Knowledge Base", "FakerAI" — and a brand name should read
            // the same in every language. Translating it also makes the admin sidebar
            // disagree with the marketplace listing the buyer purchased from.
            //
            // The description IS collected: it is a sentence about what the addon does,
            // shown in the addon manager, and reads badly left in English.
            if (isset($manifest['description']) && is_string($manifest['description'])) {
                $keys[] = trim($manifest['description']);
            }

            // [manifest section => fields within each entry that are shown to a human]
            $collections = [
                'admin_menu' => ['label'],
                'settings' => ['label', 'help'],
                'permissions' => ['name', 'group'],
                'setting_groups' => ['label', 'name'],
            ];

            foreach ($collections as $section => $fields) {
                if (! isset($manifest[$section]) || ! is_array($manifest[$section])) {
                    continue;
                }

                foreach ($manifest[$section] as $entry) {
                    if (! is_array($entry)) {
                        continue;
                    }

                    foreach ($fields as $field) {
                        if (isset($entry[$field]) && is_string($entry[$field])) {
                            $keys[] = trim($entry[$field]);
                        }
                    }
                }
            }
        }

        return $keys;
    }

    /**
     * Preset names and descriptions declared in resources/themes/*​/presets/*.json.
     *
     * @return list<string>
     */
    private static function scanThemePresets(): array
    {
        $keys = [];

        foreach (glob(resource_path('themes/*/presets/*.json')) ?: [] as $path) {
            $data = json_decode((string) file_get_contents($path), true);

            if (! is_array($data)) {
                continue;
            }

            if (isset($data['name']) && is_string($data['name']) && trim($data['name']) !== '') {
                $keys[] = trim($data['name']);
            }

            if (isset($data['description']) && is_string($data['description']) && trim($data['description']) !== '') {
                $keys[] = trim($data['description']);
            }
        }

        return $keys;
    }

    /**
     * Strings deliberately kept off the translation screen.
     */
    public static function isTranslatable(string $value): bool
    {
        // Addon product names are brand names — "AI Assistant", "AI Knowledge Base",
        // "FakerAI" — and should read the same in every language, matching the marketplace
        // listing the buyer bought from. Several are wrapped in translate() at their call
        // sites (homepage-provider labels, the assistant header), so excluding the manifest
        // field alone is not enough: they have to be blocked by value. translate() falls
        // back to the key when a catalogue has no entry, so blocking them here is precisely
        // what makes them render in English.
        if (in_array($value, static::brandNames(), true)) {
            return false;
        }

        foreach (static::blockedFragments() as $fragment) {
            if (str_contains(mb_strtolower($value), $fragment)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Addon product names, read from the manifests so the list cannot rot as addons are
     * added, renamed or removed. Memoised: isTranslatable() is called once per candidate
     * key, and the scan produces thousands.
     *
     * @return list<string>
     */
    public static function brandNames(): array
    {
        static $names = null;

        if ($names !== null) {
            return $names;
        }

        $names = [];

        foreach (glob(base_path('addons/*/addon.json')) ?: [] as $manifestPath) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);

            if (is_array($manifest) && isset($manifest['name']) && is_string($manifest['name'])) {
                $names[] = trim($manifest['name']);
            }
        }

        return $names = array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private static function blockedFragments(): array
    {
        return [
            '— type of field',
            '- type of field',
        ];
    }
}
