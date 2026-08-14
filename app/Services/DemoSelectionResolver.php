<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Builds the demo bar's "selectable demo" catalog and translates a chosen demo into
 * a set of in-memory setting overrides.
 *
 * Two kinds of demo, both discovered automatically so a new one needs no code here:
 *   - `preset:<id>`  — a theme preset JSON under the active theme's presets/ dir
 *                      (ThemePresetService lists them). Overrides the resolved
 *                      frontend theme-settings blobs from the preset file.
 *   - `addon:<slug>` — an addon-owned homepage (HomepageProviderRegistry lists the
 *                      available ones). Overrides `homepage_template` so `/` renders it.
 *
 * The overrides are applied via Setting::overrideForRequest() and never persisted —
 * demo mode blocks writes, so the whole flow is deliberately read-only.
 */
class DemoSelectionResolver
{
    /** Preset section name → the canonical setting key its values are stored under. */
    private const PRESET_SECTION_KEYS = [
        'theme' => 'frontend_theme_settings',
        'header' => 'frontend_header_settings',
        'footer' => 'frontend_footer_settings',
        'homepage' => 'frontend_homepage_settings',
        'homepage_config' => 'frontend_homepage_config',
        'tool_page' => 'frontend_tool_page_settings',
        'custom_code' => 'frontend_custom_code',
    ];

    public function __construct(
        private ThemePresetService $presets,
        private HomepageProviderRegistry $homepages,
    ) {}

    /**
     * The selectable demos, shaped for the modal. Presets lead (Default first, via
     * ThemePresetService's own ordering), addon homepages follow.
     *
     * @return array<int, array{key: string, label: string, description: string, type: string, preview: array<string, mixed>}>
     */
    public function catalog(): array
    {
        $theme = settings('active_theme', 'default');

        $catalog = [];

        foreach ($this->presets->listPresets($theme) as $preset) {
            $catalog[] = [
                'key' => 'preset:' . $preset['id'],
                'label' => $preset['name'],
                'description' => $preset['description'],
                'type' => 'preset',
                'preview' => $preset['preview'] ?? [],
                // Screenshot auto-picked from public/assets/image/demo-assets/demos/<preset-id>.<ext>.
                'image' => $this->demoImage($preset['id']),
            ];
        }

        foreach ($this->homepages->options() as $option) {
            $catalog[] = [
                'key' => 'addon:' . $option['value'],
                'label' => $option['label'],
                'description' => $option['description'],
                'type' => 'addon',
                'preview' => [],
                // Screenshot auto-picked from public/assets/image/demo-assets/demos/<addon-slug>.<ext>
                // (e.g. ai-chatbot.png, ai-image-pro.png).
                'image' => $this->demoImage($option['value']),
            ];
        }

        return $catalog;
    }

    /** Image extensions probed for a demo screenshot, in preference order. */
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    /**
     * Public URL of the screenshot for a demo, or null if none has been dropped in.
     *
     * Convention: put a file named after the demo (preset id or addon slug) in
     * public/assets/image/demo-assets/demos/ — e.g. midnight.png, ai-chatbot.jpg — and it renders
     * in the selector automatically. No DB entry, no rebuild.
     */
    private function demoImage(string $name): ?string
    {
        // Guard the filename so a slug can never escape the demos directory.
        if (! preg_match('/^[a-z0-9_-]+$/i', $name)) {
            return null;
        }

        foreach (self::IMAGE_EXTENSIONS as $ext) {
            if (File::exists(public_path("assets/image/demo-assets/demos/{$name}.{$ext}"))) {
                return "/assets/image/demo-assets/demos/{$name}.{$ext}";
            }
        }

        return null;
    }

    /**
     * Whether a selection key is one this resolver currently offers.
     */
    public function isValid(string $key): bool
    {
        foreach ($this->catalog() as $option) {
            if ($option['key'] === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalise whatever arrived in a `?demo=` param or the cookie into a catalog key,
     * or null for "no selection" (default / unknown).
     *
     * Accepts the bare demo name as well as the full key, so the shareable URL can read
     * `?demo=midnight` instead of the percent-encoded `?demo=preset%3Amidnight`. Presets
     * are listed before addon homepages, so a bare name that both offer resolves to the
     * preset.
     */
    public function resolveKey(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === 'default') {
            return null;
        }

        foreach ($this->catalog() as $option) {
            if ($option['key'] === $value || $this->shortName($option['key']) === $value) {
                return $option['key'];
            }
        }

        return null;
    }

    /**
     * The URL-friendly half of a selection key: `preset:midnight` → `midnight`. This is
     * what the `?demo=` param carries and what a screenshot file is named after.
     */
    public function shortName(string $key): string
    {
        return str_contains($key, ':') ? explode(':', $key, 2)[1] : $key;
    }

    /**
     * The in-memory setting overrides for a selection key. Empty array means "no
     * override" — i.e. fall back to whatever the site is actually configured with.
     *
     * @return array<string, mixed>
     */
    public function overridesFor(?string $key): array
    {
        if (! $key || ! $this->isValid($key)) {
            return [];
        }

        [$type, $id] = array_pad(explode(':', $key, 2), 2, '');

        return match ($type) {
            'preset' => $this->presetOverrides($id),
            'addon' => ['homepage_template' => $id],
            default => [],
        };
    }

    /**
     * Turn a preset's JSON `settings` sections into setting-key overrides. Mirrors what
     * ThemePresetService::applyPreset() persists, but into the request-scoped override
     * map instead of the database. Also pins `homepage_template` back to the theme's own
     * homepage, so switching from an addon demo to a preset restores the theme homepage.
     *
     * @return array<string, mixed>
     */
    private function presetOverrides(string $id): array
    {
        $theme = settings('active_theme', 'default');
        $path = resource_path("themes/{$theme}/presets/{$id}.json");

        if (! File::exists($path)) {
            return [];
        }

        $data = json_decode((string) File::get($path), true);
        $sections = is_array($data['settings'] ?? null) ? $data['settings'] : [];

        $overrides = ['homepage_template' => 'default'];

        foreach ($sections as $section => $values) {
            if (isset(self::PRESET_SECTION_KEYS[$section]) && is_array($values)) {
                $overrides[self::PRESET_SECTION_KEYS[$section]] = $values;
            }
        }

        return $overrides;
    }
}
