<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * ThemeService — loads active theme, merges settings, provides theme_setting().
 * Ref: AI_SaaS_Master_Prompt Part 2.2.
 */
class ThemeService
{
    private string $themesPath;

    private ?array $activeThemeConfig = null;

    public function __construct()
    {
        $this->themesPath = resource_path('themes');
    }

    /**
     * Get the active theme slug.
     */
    public function getActiveTheme(): string
    {
        return settings('active_theme', 'default');
    }

    /**
     * Get all available themes by scanning the themes directory.
     */
    public function getAvailableThemes(): array
    {
        $themes = [];

        if (! File::isDirectory($this->themesPath)) {
            return $themes;
        }

        foreach (File::directories($this->themesPath) as $dir) {
            $settingsFile = $dir.'/settings.json';
            if (File::exists($settingsFile)) {
                $config = json_decode(File::get($settingsFile), true);
                if ($config && isset($config['slug'])) {
                    $config['path'] = $dir;
                    $config['is_active'] = $config['slug'] === $this->getActiveTheme();
                    $config['license_ok'] = $this->checkLicenseRequirement($config);
                    $themes[] = $config;
                }
            }
        }

        return $themes;
    }

    /**
     * Get a specific theme's configuration.
     */
    public function getThemeConfig(string $slug): ?array
    {
        $settingsFile = $this->themesPath.'/'.$slug.'/settings.json';

        if (! File::exists($settingsFile)) {
            return null;
        }

        $config = json_decode(File::get($settingsFile), true);
        $config['path'] = $this->themesPath.'/'.$slug;
        $config['is_active'] = $slug === $this->getActiveTheme();
        $config['license_ok'] = $this->checkLicenseRequirement($config);

        return $config;
    }

    /**
     * Get the active theme's full config (cached).
     */
    public function getActiveThemeConfig(): ?array
    {
        if ($this->activeThemeConfig) {
            return $this->activeThemeConfig;
        }

        $this->activeThemeConfig = Cache::remember('active_theme_config', 3600, function () {
            return $this->getThemeConfig($this->getActiveTheme());
        });

        return $this->activeThemeConfig;
    }

    /**
     * Activate a theme.
     */
    public function activate(string $slug): bool
    {
        $config = $this->getThemeConfig($slug);
        if (! $config) {
            return false;
        }

        if (! $this->checkLicenseRequirement($config)) {
            return false;
        }

        settings_set('active_theme', $slug);
        Cache::forget('active_theme_config');
        $this->activeThemeConfig = null;

        return true;
    }

    /**
     * Get a theme setting value (stored in settings table with theme prefix).
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        $theme = $this->getActiveTheme();

        return settings("theme_{$theme}_{$key}", $default);
    }

    /**
     * Set a theme setting value.
     */
    public function setSetting(string $key, mixed $value): void
    {
        $theme = $this->getActiveTheme();
        settings_set("theme_{$theme}_{$key}", $value, 'string', 'theme');
    }

    /**
     * Get all settings for a theme with their current values (from DB or defaults).
     */
    public function getThemeSettings(string $slug): array
    {
        $config = $this->getThemeConfig($slug);
        if (! $config || empty($config['settings'])) {
            return [];
        }

        return array_map(function ($setting) use ($slug) {
            $setting['value'] = settings("theme_{$slug}_{$setting['key']}", $setting['default'] ?? null);

            return $setting;
        }, $config['settings']);
    }

    /**
     * Save theme settings (bulk).
     */
    public function saveThemeSettings(string $slug, array $values): void
    {
        foreach ($values as $key => $value) {
            settings_set("theme_{$slug}_{$key}", $value, 'string', 'theme');
        }
        Cache::forget('active_theme_config');
    }

    /**
     * Check if a theme's license requirement is met.
     *
     * Premium themes (declaring an envato_item_id in their manifest) need their own
     * per-theme license, verified against the License Server — exactly like addons.
     * Bundled themes (e.g. `default`, no item id) are gated by the core license tier.
     */
    private function checkLicenseRequirement(array $config): bool
    {
        if (! license_verified()) {
            return false;
        }

        if (! empty($config['envato_item_id'])) {
            return app(ThemeLicenseService::class)->isLicensed($config['slug'] ?? '');
        }

        $required = $config['requires_license'] ?? 1;

        return get_license_type() >= $required;
    }
}
