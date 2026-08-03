<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class ThemeSettingsService
{
    private const THEME_SETTINGS_KEY = 'frontend_theme_settings';

    private const HEADER_SETTINGS_KEY = 'frontend_header_settings';

    private const FOOTER_SETTINGS_KEY = 'frontend_footer_settings';

    private const HOMEPAGE_SETTINGS_KEY = 'frontend_homepage_settings';

    private const HOMEPAGE_CONFIG_KEY = 'frontend_homepage_config';

    private const CUSTOM_CODE_SETTINGS_KEY = 'frontend_custom_code';

    private const TOOL_PAGE_SETTINGS_KEY = 'frontend_tool_page_settings';

    private ?array $defaultsCache = null;

    /**
     * Load and cache the defaults from settings.json.
     */
    private function loadDefaults(): array
    {
        if ($this->defaultsCache !== null) {
            return $this->defaultsCache;
        }

        $activeTheme = settings('active_theme', 'default');
        $path = resource_path("themes/{$activeTheme}/settings.json");

        if (! File::exists($path)) {
            $path = resource_path('themes/default/settings.json');
        }

        if (! File::exists($path)) {
            $this->defaultsCache = [];

            return $this->defaultsCache;
        }

        $config = json_decode(File::get($path), true);
        $this->defaultsCache = $config['defaults'] ?? [];

        return $this->defaultsCache;
    }

    /**
     * Get defaults for a specific section.
     */
    private function getDefaults(string $section): array
    {
        return $this->loadDefaults()[$section] ?? [];
    }

    // ─── Stored getters ───

    public function getStoredThemeSettings(): array
    {
        return $this->normalizeStoredSettings(settings(self::THEME_SETTINGS_KEY, []));
    }

    public function getStoredHeaderSettings(): array
    {
        return $this->normalizeStoredSettings(settings(self::HEADER_SETTINGS_KEY, []));
    }

    public function getStoredFooterSettings(): array
    {
        return $this->normalizeStoredSettings(settings(self::FOOTER_SETTINGS_KEY, []));
    }

    public function getStoredHomepageSettings(): array
    {
        return $this->normalizeStoredSettings(settings(self::HOMEPAGE_SETTINGS_KEY, []));
    }

    public function getStoredCustomCodeSettings(): array
    {
        return $this->normalizeStoredSettings(settings(self::CUSTOM_CODE_SETTINGS_KEY, []));
    }

    public function getStoredToolPageSettings(): array
    {
        return $this->normalizeStoredSettings(settings(self::TOOL_PAGE_SETTINGS_KEY, []));
    }

    public function getStoredHomepageConfig(): array
    {
        return $this->normalizeStoredSettings(settings(self::HOMEPAGE_CONFIG_KEY, []));
    }

    // ─── Resolved (defaults + stored overrides) ───

    public function getResolvedFrontendTheme(): array
    {
        return array_replace($this->getDefaults('theme'), $this->filterThemeSettings($this->getStoredThemeSettings()));
    }

    public function getResolvedFrontendHeader(): array
    {
        return array_replace_recursive($this->getDefaults('header'), $this->filterHeaderSettings($this->getStoredHeaderSettings()));
    }

    public function getResolvedFrontendFooter(): array
    {
        return array_replace($this->getDefaults('footer'), $this->filterFooterSettings($this->getStoredFooterSettings()));
    }

    public function getResolvedFrontendHomepage(): array
    {
        return array_replace($this->getDefaults('homepage'), $this->filterHomepageSettings($this->getStoredHomepageSettings()));
    }

    public function getResolvedFrontendToolPage(): array
    {
        return array_replace($this->getDefaults('tool_page'), $this->filterToolPageSettings($this->getStoredToolPageSettings()));
    }

    /**
     * The glyph an admin chose to sit between a frontend page title and the site name.
     * Bare glyph only — callers add the surrounding spaces, so a stored value can never
     * introduce double spacing or leave the separator flush against the title.
     *
     * Frontend only: the admin panel's own titles stay on their fixed " - ".
     *
     * MUST stay in sync with formatDocumentTitle() in resources/js/app.ts, which reads
     * the same key off the shared `appearanceThemeSettings` prop. The server composes
     * the <title> for the initial HTML and the client callback recomposes it on mount;
     * if the two disagree the tab visibly flips after hydration.
     */
    public function getTitleSeparator(): string
    {
        $separator = trim((string) ($this->getResolvedFrontendTheme()['title_separator'] ?? ''));

        return $separator !== '' ? $separator : '|';
    }

    public function getResolvedFrontendHomepageConfig(): array
    {
        $stored = $this->getStoredHomepageConfig();
        $defaults = $this->getDefaults('homepage_config');

        $mergedSettings = array_replace_recursive(
            $defaults['settings'] ?? [],
            $stored['settings'] ?? []
        );

        $storedSections = $stored['sections'] ?? [];

        $storedByType = [];
        foreach ($storedSections as $section) {
            if (isset($section['type']) && isset($section['config'])) {
                $storedByType[$section['type']] = $section['config'];
            }
        }

        $mergedSections = [];
        foreach ($defaults['sections'] ?? [] as $section) {
            $type = $section['type'] ?? '';
            $config = $section['config'] ?? [];

            if (isset($storedByType[$type])) {
                $mergedSections[] = [
                    'type' => $type,
                    'config' => $this->replaceConfigRecursive($config, $storedByType[$type]),
                ];
                unset($storedByType[$type]);
            } else {
                $mergedSections[] = $section;
            }
        }

        foreach ($storedByType as $type => $config) {
            $mergedSections[] = ['type' => $type, 'config' => $config];
        }

        return [
            'settings' => $mergedSettings,
            'sections' => $mergedSections,
        ];
    }

    // ─── Save methods ───

    public function saveHomepageConfig(array $config): array
    {
        settings_set(self::HOMEPAGE_CONFIG_KEY, $config, 'json', 'appearance');

        return $config;
    }

    public function saveThemeSettings(array $settings): array
    {
        $filtered = $this->filterThemeSettings($settings);
        settings_set(self::THEME_SETTINGS_KEY, $filtered, 'json', 'appearance');

        return $filtered;
    }

    public function saveHeaderSettings(array $settings): array
    {
        $filtered = $this->filterHeaderSettings($settings);
        settings_set(self::HEADER_SETTINGS_KEY, $filtered, 'json', 'appearance');

        return $filtered;
    }

    public function saveFooterSettings(array $settings): array
    {
        $filtered = $this->filterFooterSettings($settings);
        settings_set(self::FOOTER_SETTINGS_KEY, $filtered, 'json', 'appearance');

        return $filtered;
    }

    public function saveHomepageSettings(array $settings): array
    {
        $filtered = $this->filterHomepageSettings($settings);
        settings_set(self::HOMEPAGE_SETTINGS_KEY, $filtered, 'json', 'appearance');

        return $filtered;
    }

    public function saveCustomCodeSettings(array $settings): array
    {
        $filtered = $this->filterCustomCodeSettings($settings);
        settings_set(self::CUSTOM_CODE_SETTINGS_KEY, $filtered, 'json', 'appearance');

        return $filtered;
    }

    public function saveToolPageSettings(array $settings): array
    {
        $filtered = $this->filterToolPageSettings($settings);
        settings_set(self::TOOL_PAGE_SETTINGS_KEY, $filtered, 'json', 'appearance');

        return $filtered;
    }

    // ─── Filter methods (use settings.json defaults) ───

    private function normalizeStoredSettings(mixed $settings): array
    {
        return is_array($settings) ? $settings : [];
    }

    private function filterThemeSettings(array $settings): array
    {
        return array_intersect_key($settings, $this->getDefaults('theme'));
    }

    private function filterHeaderSettings(array $settings): array
    {
        $defaults = $this->getDefaults('header');

        $group = function (string $name) use ($settings, $defaults): array {
            $groupDefaults = $defaults[$name] ?? [];
            $values = is_array($settings[$name] ?? null)
                ? array_intersect_key($settings[$name], $groupDefaults)
                : [];

            return $this->castToDefaultTypes($values, $groupDefaults);
        };

        return [
            'desktop' => $group('desktop'),
            'mobile_top' => $group('mobile_top'),
            'mobile_bottom' => $group('mobile_bottom'),
        ];
    }

    private function filterFooterSettings(array $settings): array
    {
        $defaults = $this->getDefaults('footer');

        return $this->castToDefaultTypes(array_intersect_key($settings, $defaults), $defaults);
    }

    private function filterHomepageSettings(array $settings): array
    {
        $defaults = $this->getDefaults('homepage');

        return $this->castToDefaultTypes(array_intersect_key($settings, $defaults), $defaults);
    }

    /**
     * The homepage and footer forms upload images, so they are sent as multipart — an
     * encoding with no boolean type, which turns every toggle into the string "1"/"0".
     * Restore each value to the type its default declares.
     *
     * Applied on both save and read by every section filter, so rows already written in the
     * stringified shape resolve correctly without a migration. Without it a stored "0" reads
     * as a JS-truthy string on the frontend — the exact inversion that hid the homepage's
     * back-to-top button — and each consumer has to hand-roll its own truthiness check.
     *
     * Booleans only: integers such as `height` and `footer_vertical_padding` are interpolated
     * into CSS lengths where a numeric string behaves identically, and coercing them would
     * turn a cleared field into 0 rather than leaving it to fall back to the default.
     */
    private function castToDefaultTypes(array $settings, array $defaults): array
    {
        foreach ($settings as $key => $value) {
            if (is_bool($defaults[$key] ?? null) && ! is_bool($value)) {
                $settings[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $settings;
    }

    private function filterCustomCodeSettings(array $settings): array
    {
        return array_intersect_key($settings, $this->getDefaults('custom_code'));
    }

    private function filterToolPageSettings(array $settings): array
    {
        return array_intersect_key($settings, $this->getDefaults('tool_page'));
    }

    // ─── Restore defaults ───

    /**
     * Delete stored settings for a section so the next render falls back to defaults.
     * Sections: theme, header, footer, homepage, custom_code, tool_page
     */
    public function restoreDefaults(string $section): void
    {
        $keyMap = [
            'theme'       => self::THEME_SETTINGS_KEY,
            'header'      => self::HEADER_SETTINGS_KEY,
            'footer'      => self::FOOTER_SETTINGS_KEY,
            'homepage'    => self::HOMEPAGE_SETTINGS_KEY,
            'custom_code' => self::CUSTOM_CODE_SETTINGS_KEY,
            'tool_page'   => self::TOOL_PAGE_SETTINGS_KEY,
        ];

        if (isset($keyMap[$section])) {
            settings_set($keyMap[$section], null, 'json', 'appearance');
        }

        // Also clear homepage_config when restoring homepage
        if ($section === 'homepage') {
            settings_set(self::HOMEPAGE_CONFIG_KEY, null, 'json', 'appearance');
        }
    }

    /**
     * Recursively replace config, but completely overwrite sequential arrays (lists)
     * instead of merging them index-by-index.
     */
    private function replaceConfigRecursive(array $default, array $stored): array
    {
        $result = $default;
        foreach ($stored as $key => $value) {
            if (is_array($value) && isset($default[$key]) && is_array($default[$key])) {
                if (array_is_list($value)) {
                    $result[$key] = $value;
                } else {
                    $result[$key] = $this->replaceConfigRecursive($default[$key], $value);
                }
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
