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
    /** Where the pre-apply snapshot of the frontend look lives. */
    private const BACKUP_KEY = 'theme_preset_backup';

    /** Its own blob group — see the note on this key in Setting::BLOB_GROUP_KEYS. */
    private const BACKUP_GROUP = 'preset_backup';

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
                // Merged over the theme's own palette, so a preset that declares no preview —
                // or only some of it — still paints an accurate card instead of falling back
                // to the hard-coded greys in the Vue. Keeps `default.json` from having to
                // restate colours it inherits from settings.json anyway.
                'preview' => array_replace(
                    $this->themePreviewDefaults($slug),
                    is_array($data['preview'] ?? null) ? $data['preview'] : []
                ),
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

        // Snapshot the outgoing look first — this is the only moment it still exists.
        $this->captureBackup($slug, $id, (string) ($data['name'] ?? $id));

        $this->writeSections($sections, preserveHomepageMeta: true);

        // Remembered only so the settings page can flag the applied card — it drifts
        // the moment an admin edits a section by hand, and nothing reads it as truth.
        settings_set('active_theme_preset', $id, 'string', 'appearance');

        $this->flush();

        return true;
    }

    /** Theme slug => the preview swatches implied by that theme's factory palette. */
    private array $previewDefaults = [];

    /**
     * The card swatches a preset inherits when it declares none of its own.
     *
     * Read from the named theme's settings.json rather than ThemeSettingsService, for two
     * reasons: listPresets() is given a slug that need not be the active theme, and the
     * resolved settings would carry the admin's current overrides — showing those on a
     * preset card would preview the site as it is, not as the card would leave it.
     *
     * @return array<string, string>
     */
    private function themePreviewDefaults(string $slug): array
    {
        if (isset($this->previewDefaults[$slug])) {
            return $this->previewDefaults[$slug];
        }

        $path = resource_path("themes/{$slug}/settings.json");
        $theme = File::exists($path)
            ? (json_decode((string) File::get($path), true)['defaults']['theme'] ?? null)
            : null;

        if (! is_array($theme)) {
            return $this->previewDefaults[$slug] = [];
        }

        $map = [
            'mode' => 'theme_default_mode',
            'primary' => 'primary_color',
            'secondary' => 'secondary_color',
            'accent' => 'accent_color',
            'bg' => 'bg_color',
            'heading' => 'heading_color',
            'text' => 'body_text_color',
        ];

        $preview = [];
        foreach ($map as $key => $source) {
            if (isset($theme[$source]) && $theme[$source] !== '') {
                $preview[$key] = (string) $theme[$source];
            }
        }

        return $this->previewDefaults[$slug] = $preview;
    }

    /**
     * Put back the look captured by the last applyPreset(). One level deep on purpose:
     * this undoes the most recent apply, not a history of them.
     */
    public function restoreBackup(): bool
    {
        $backup = settings(self::BACKUP_KEY);
        $sections = is_array($backup) && is_array($backup['sections'] ?? null) ? $backup['sections'] : null;

        if ($sections === null) {
            return false;
        }

        // No meta-preserving here: the snapshot already holds the exact homepage_config
        // that was live, SEO copy included, so it goes back verbatim.
        $this->writeSections($sections, preserveHomepageMeta: false);

        $previous = $backup['previous_preset'] ?? null;
        settings_set('active_theme_preset', is_string($previous) && $previous !== '' ? $previous : null, 'string', 'appearance');

        // Consumed — the button disappears until the next apply, so nobody can "undo"
        // their way into a state they already undid.
        settings_set(self::BACKUP_KEY, null, 'json', self::BACKUP_GROUP);

        $this->flush();

        return true;
    }

    /**
     * What the restore banner needs: whether a snapshot exists and what applying it would
     * roll back. Null when there is nothing to restore.
     *
     * @return array{captured_at: ?string, replaced_by: ?string, replaced_by_name: ?string, previous_preset: ?string, previous_preset_name: ?string}|null
     */
    public function backupSummary(): ?array
    {
        $backup = settings(self::BACKUP_KEY);

        if (! is_array($backup) || ! is_array($backup['sections'] ?? null)) {
            return null;
        }

        return [
            'captured_at' => $backup['captured_at'] ?? null,
            'replaced_by' => $backup['replaced_by'] ?? null,
            'replaced_by_name' => $backup['replaced_by_name'] ?? null,
            'previous_preset' => $backup['previous_preset'] ?? null,
            'previous_preset_name' => $backup['previous_preset_name'] ?? null,
        ];
    }

    /**
     * Store the live frontend look so restoreBackup() can put it back.
     *
     * custom_code is deliberately absent: no preset writes it, so it is never at risk and
     * capturing it would only risk handing back a stale copy of the buyer's own scripts.
     */
    private function captureBackup(string $slug, string $presetId, string $presetName): void
    {
        $previous = settings('active_theme_preset');
        $previous = is_string($previous) && $previous !== '' ? $previous : null;

        settings_set(self::BACKUP_KEY, [
            'captured_at' => now()->toIso8601String(),
            'replaced_by' => $presetId,
            'replaced_by_name' => $presetName,
            'previous_preset' => $previous,
            // Resolved now rather than on read, so the label survives the preset file
            // being renamed or dropped from a later release.
            'previous_preset_name' => $previous ? $this->presetName($slug, $previous) : null,
            'sections' => [
                'theme' => $this->themeSettings->getStoredThemeSettings(),
                'header' => $this->themeSettings->getStoredHeaderSettings(),
                'footer' => $this->themeSettings->getStoredFooterSettings(),
                'homepage' => $this->themeSettings->getStoredHomepageSettings(),
                'homepage_config' => $this->themeSettings->getStoredHomepageConfig(),
                'tool_page' => $this->themeSettings->getStoredToolPageSettings(),
            ],
        ], 'json', self::BACKUP_GROUP);
    }

    /**
     * Route each section through the matching ThemeSettingsService save method, which
     * key-filters against settings.json — so neither a preset nor a snapshot can persist
     * an unknown key. Sections not present are left exactly as they are.
     */
    private function writeSections(array $sections, bool $preserveHomepageMeta): void
    {
        foreach ($sections as $section => $values) {
            if (! is_array($values)) {
                continue;
            }

            match ($section) {
                'theme' => $this->themeSettings->saveThemeSettings($values),
                'header' => $this->themeSettings->saveHeaderSettings($values),
                'footer' => $this->themeSettings->saveFooterSettings($values),
                'homepage' => $this->themeSettings->saveHomepageSettings($values),
                'homepage_config' => $this->themeSettings->saveHomepageConfig(
                    $preserveHomepageMeta ? $this->preserveHomepageMeta($values) : $values
                ),
                'tool_page' => $this->themeSettings->saveToolPageSettings($values),
                'custom_code' => $this->themeSettings->saveCustomCodeSettings($values),
                default => null,
            };
        }
    }

    /** The display name in a preset file, falling back to its id. */
    private function presetName(string $slug, string $id): string
    {
        if (! preg_match('/^[a-z0-9_-]+$/', $id)) {
            return $id;
        }

        $path = resource_path("themes/{$slug}/presets/{$id}.json");

        if (! File::exists($path)) {
            return $id;
        }

        $data = json_decode((string) File::get($path), true);

        return is_array($data) ? (string) ($data['name'] ?? $id) : $id;
    }

    private function flush(): void
    {
        Setting::flushCache();
        Cache::forget('active_theme_config');
    }

    /**
     * homepage_config carries two unrelated things: `sections`, which is the look a preset
     * exists to change, and `settings`, which holds the admin's own meta title/description.
     * saveHomepageConfig replaces the whole blob, so a preset that only ships sections would
     * silently reset their SEO copy to the theme's factory strings. Carry the stored
     * `settings` across, letting a preset override only the keys it actually declares.
     *
     * `sections` is deliberately left alone — replacing it wholesale is what keeps switching
     * between presets from leaving the previous one's styling behind.
     */
    private function preserveHomepageMeta(array $config): array
    {
        $stored = $this->themeSettings->getStoredHomepageConfig();

        $config['settings'] = array_replace_recursive(
            is_array($stored['settings'] ?? null) ? $stored['settings'] : [],
            is_array($config['settings'] ?? null) ? $config['settings'] : [],
        );

        return $config;
    }
}
