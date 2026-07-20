<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Discovers and applies theme presets — bundled JSON snapshots that mirror the
 * `defaults` shape of a theme's settings.json. Dropping a new `*.json` file into
 * a theme's `presets/` directory makes it appear on the settings page automatically.
 */
class ThemePresetService
{
    public function __construct(
        private ThemeSettingsService $themeSettings,
    ) {}

    /**
     * The presets bundled with a theme, each reduced to the metadata the settings
     * page needs to render a selectable card. Malformed or unreadable files are
     * skipped rather than aborting the whole list.
     *
     * @return array<int, array{id: string, name: string, description: string, preview: array<string, mixed>, sections: array<int, string>}>
     */
    public function listPresets(string $slug): array
    {
        $dir = resource_path("themes/{$slug}/presets");

        if (! File::isDirectory($dir)) {
            return [];
        }

        $presets = [];

        foreach (File::glob($dir . '/*.json') as $path) {
            $id = basename($path, '.json');

            // Same charset the apply path enforces — a card the admin cannot apply
            // has no business being listed.
            if (! preg_match('/^[a-z0-9_-]+$/', $id)) {
                continue;
            }

            $data = json_decode((string) File::get($path), true);

            if (! is_array($data) || ! is_array($data['settings'] ?? null)) {
                continue;
            }

            $presets[] = [
                'id' => $id,
                'name' => (string) ($data['name'] ?? ucfirst($id)),
                'description' => (string) ($data['description'] ?? ''),
                'preview' => is_array($data['preview'] ?? null) ? $data['preview'] : [],
                'sections' => array_keys($data['settings']),
            ];
        }

        // 'default' always leads; everything else sorts by display name.
        usort($presets, function (array $a, array $b): int {
            if ($a['id'] === 'default') {
                return -1;
            }
            if ($b['id'] === 'default') {
                return 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return $presets;
    }

    /**
     * Apply a preset. Each section it defines is routed through the matching
     * ThemeSettingsService save method, which key-filters against settings.json —
     * so a preset can never persist an unknown key. Sections the file omits are
     * left exactly as they are.
     */
    public function applyPreset(string $slug, string $id): bool
    {
        if (! preg_match('/^[a-z0-9_-]+$/', $id)) {
            return false;
        }

        $path = resource_path("themes/{$slug}/presets/{$id}.json");

        if (! File::exists($path)) {
            return false;
        }

        $data = json_decode((string) File::get($path), true);
        $sections = is_array($data['settings'] ?? null) ? $data['settings'] : null;

        if (! is_array($sections)) {
            return false;
        }

        foreach ($sections as $section => $values) {
            if (! is_array($values)) {
                continue;
            }

            match ($section) {
                'theme' => $this->themeSettings->saveThemeSettings($values),
                'header' => $this->themeSettings->saveHeaderSettings($values),
                'footer' => $this->themeSettings->saveFooterSettings($values),
                'homepage' => $this->themeSettings->saveHomepageSettings($values),
                'homepage_config' => $this->themeSettings->saveHomepageConfig($values),
                'tool_page' => $this->themeSettings->saveToolPageSettings($values),
                'custom_code' => $this->themeSettings->saveCustomCodeSettings($values),
                default => null,
            };
        }

        // Remembered only so the settings page can flag the applied card — it drifts
        // the moment an admin edits a section by hand, and nothing reads it as truth.
        settings_set('active_theme_preset', $id, 'string', 'appearance');

        Setting::flushCache();
        Cache::forget('theme-variables-css');
        Cache::forget('active_theme_config');

        return true;
    }
}
