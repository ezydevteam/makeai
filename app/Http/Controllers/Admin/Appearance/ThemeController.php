<?php

namespace App\Http\Controllers\Admin\Appearance;

use App\Http\Controllers\Controller;
use App\Services\HomepageProviderRegistry;
use App\Services\ThemePresetService;
use App\Services\ThemeSettingsService;
use App\Services\ThemeService;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use ZipArchive;
use Illuminate\Support\Facades\Storage;
use App\Models\AiTool;
use App\Models\Menu;
use App\Models\Category;
use App\Models\FaqCategory;

/**
 * Admin Theme Management Controller.
 */
class ThemeController extends Controller
{
    public function __construct(
        private ThemeService $themeService,
        private ThemeSettingsService $frontendPresetService,
        private ThemePresetService $themePresetService,
    ) {}

    /**
     * List all available themes.
     */
    public function themes()
    {
        $themeLicenseService = app(\App\Services\ThemeLicenseService::class);
        $themes = $this->themeService->getAvailableThemes();

        $themes = array_map(function ($theme) use ($themeLicenseService) {
            $slug = $theme['slug'];
            $theme['license'] = $themeLicenseService->getLicenseInfo($slug);
            $theme['envato_item_id'] = $theme['envato_item_id'] ?? null;
            $theme['update_available'] = (bool) settings("theme_{$slug}_update_available", false);
            $theme['latest_version'] = settings("theme_{$slug}_latest_version");
            $theme['update_changelog'] = settings("theme_{$slug}_update_changelog");
            $theme['update_checked_at'] = settings("theme_{$slug}_update_checked_at");
            return $theme;
        }, $themes);

        return Inertia::render('Admin/Appearance/Themes', [
            'themes' => $themes,
            'activeTheme' => settings('active_theme', 'default'),
        ]);
    }

    /**
     * Install a theme from an uploaded zip.
     * Extracts to resources/themes/{slug}.
     */
    public function installTheme(Request $request)
    {
        abort_unless(auth('admin')->user()?->hasAnyPermission(['themes.manage', 'settings.manage', 'addons.manage']), 403);

        $request->validate([
            'theme_zip' => ['required', 'file', 'mimes:zip', 'max:51200'],
        ]);

        $zip = new ZipArchive;
        if ($zip->open($request->file('theme_zip')->getRealPath()) !== true) {
            return back()->with('error', translate('Failed to open the uploaded zip file.'));
        }

        // Single lowercase root directory = the theme slug.
        $rootDirs = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $parts = explode('/', trim((string) $zip->getNameIndex($i), '/'));
            if (($parts[0] ?? '') !== '' && $parts[0] !== '__MACOSX') {
                $rootDirs[$parts[0]] = true;
            }
        }
        $slug = count($rootDirs) === 1 ? array_key_first($rootDirs) : null;
        if (! $slug || ! preg_match('/^[a-z0-9-]+$/', $slug)) {
            $zip->close();
            return back()->with('error', translate('Invalid theme zip: expected a single lowercase root directory.'));
        }

        // Zip Slip protection + manifest presence.
        $hasManifest = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_starts_with($name, '/') || str_starts_with($name, '\\') || str_contains($name, '../') || str_contains($name, '..\\')) {
                $zip->close();
                return back()->with('error', translate('Invalid theme zip: unsafe paths detected.'));
            }
            if (! str_starts_with($name, $slug . '/') && $name !== $slug . '/') {
                $zip->close();
                return back()->with('error', translate('Invalid theme zip: files must live inside the root directory.'));
            }
            if (str_starts_with($name, $slug . '/') && basename($name) === 'settings.json') {
                $hasManifest = true;
            }
        }
        if (! $hasManifest) {
            $zip->close();
            return back()->with('error', translate('Invalid theme zip — no settings.json manifest found inside.'));
        }

        $tempDir = sys_get_temp_dir() . '/theme_upload_' . uniqid();
        @mkdir($tempDir, 0755, true);
        $zip->extractTo($tempDir);
        $zip->close();

        $sourceDir = $tempDir . '/' . $slug;
        if (! is_dir($sourceDir) || ! file_exists($sourceDir . '/settings.json')) {
            File::deleteDirectory($tempDir);
            return back()->with('error', translate('Theme package is missing its settings.json.'));
        }

        File::copyDirectory($sourceDir, resource_path('themes/' . $slug));
        File::deleteDirectory($tempDir);
        \Illuminate\Support\Facades\Cache::forget('active_theme_config');

        return back()->with('success', translate('Theme installed. Verify its license (if required) and activate it below.'));
    }

    /**
     * Verify a premium theme's purchase code against the License Server.
     */
    public function verifyThemeLicense(Request $request, string $slug)
    {
        $config = $this->themeService->getThemeConfig($slug);
        if (! $config) {
            return response()->json(['error' => translate('Theme not found.')], 404);
        }
        if (empty($config['envato_item_id'])) {
            return response()->json(['error' => translate('This theme does not require a separate license.')], 422);
        }

        $request->validate(['purchase_code' => ['required', 'string', 'max:64']]);

        $result = app(\App\Services\ThemeLicenseService::class)->verify($slug, $request->input('purchase_code'));

        if (! $result->valid) {
            return response()->json(['error' => $result->error, 'error_code' => $result->errorCode], 422);
        }

        return response()->json([
            'success' => true,
            'buyer' => $result->buyer,
            'type' => $result->type,
            'message' => translate('License verified for :buyer', ['buyer' => $result->buyer]),
        ]);
    }

    /**
     * On-demand theme update check. Inertia-friendly.
     */
    public function checkThemeUpdates()
    {
        $key = 'theme-update-check:' . (auth('admin')->id() ?? request()->ip());
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 6)) {
            return back()->with('error', translate('Please wait :seconds seconds before checking for updates again.', ['seconds' => \Illuminate\Support\Facades\RateLimiter::availableIn($key)]));
        }
        \Illuminate\Support\Facades\RateLimiter::hit($key, 60);

        $results = collect(app(\App\Services\ThemeLicenseService::class)->checkAllThemeUpdates());
        $available = $results->filter(fn ($r) => ! empty($r['update_available']))->count();
        $errored = $results->filter(fn ($r) => ! empty($r['error']))->count();

        if ($results->isEmpty()) {
            return back()->with('success', translate('No licensed themes to check for updates.'));
        }
        if ($available > 0) {
            return back()->with('success', translate(':count theme update(s) available.', ['count' => $available]));
        }
        if ($errored > 0) {
            return back()->with('error', translate('Could not reach the license server to check :count theme(s). Please try again later.', ['count' => $errored]));
        }

        return back()->with('success', translate('All themes are up to date.'));
    }

    /**
     * Activate a theme.
     */
    public function activateTheme(Request $request, string $slug)
    {
        if ($this->themeService->activate($slug)) {
            return back()->with('success', translate('Theme :theme activated successfully.', ['theme' => $slug]));
        }

        return back()->with('error', translate('Failed to activate theme. Check license requirements.'));
    }

    /**
     * Get theme settings page.
     */
    public function themeSettings(string $slug)
    {
        $config = $this->themeService->getThemeConfig($slug);
        if (! $config) {
            return back()->with('error', translate('Theme not found.'));
        }

        if ($slug === 'default') {
            $menus = Menu::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug']);

            return Inertia::render('Admin/Appearance/DefaultThemeSettings', [
                'adZones' => config('ads.zones'),
                'theme' => $config,
                'settings' => array_merge(
                    $this->frontendPresetService->getResolvedFrontendTheme(),
                    [
                        'site_logo_light'    => settings('site_logo_light', ''),
                        'site_logo_dark'     => settings('site_logo_dark', ''),
                        'site_favicon_ico'   => settings('site_favicon_ico', ''),
                        'site_favicon_png'   => settings('site_favicon_png', ''),
                        'site_og_image'      => settings('site_og_image', ''),
                    ]
                ),
                'frontendThemeSettings' => $this->frontendPresetService->getStoredThemeSettings(),
                'frontendHeaderSettings' => $this->frontendPresetService->getStoredHeaderSettings(),
                'frontendFooterSettings' => $this->frontendPresetService->getStoredFooterSettings(),
                'frontendHomepageSettings' => $this->frontendPresetService->getStoredHomepageSettings(),
                'frontendHomepageConfig' => $this->frontendPresetService->getResolvedFrontendHomepageConfig(),
                // Addons that can take over `/`. Empty unless one is active, which is
                // what keeps the picker off the page on a stock install.
                'homepageProviders' => app(HomepageProviderRegistry::class)->options(),
                'homepageTemplate' => settings('homepage_template', 'default'),
                'themePresets' => $this->themePresetService->listPresets($slug),
                'activePreset' => settings('active_theme_preset'),
                'frontendCustomCodeSettings' => $this->frontendPresetService->getStoredCustomCodeSettings(),
                'frontendToolPageSettings' => $this->frontendPresetService->getStoredToolPageSettings(),
                'menus' => $menus,
                'aiCategories' => Category::aiTools()
                    ->active()
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug']),
                'faqCategories' => FaqCategory::orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'allTools' => AiTool::active()
                    ->with('category:id,name')
                    ->orderBy('name')
                    ->get(['slug', 'name', 'description', 'icon', 'color', 'category_id'])
                    ->map(function (AiTool $tool): array {
                        return [
                            'slug' => $tool->slug,
                            'name' => $tool->name,
                            'description' => $tool->description,
                            'icon' => $tool->icon,
                            'color' => $tool->color,
                            'category' => $tool->category?->name,
                        ];
                    })
                    ->values()
                    ->toArray(),
            ]);
        }

        return Inertia::render('Admin/Appearance/ThemeSettings', [
            'theme' => $config,
            'settings' => $this->themeService->getThemeSettings($slug),
        ]);
    }

    /**
     * Save theme settings.
     */
    public function saveThemeSettings(Request $request, string $slug)
    {
        $config = $this->themeService->getThemeConfig($slug);
        if (! $config) {
            return back()->with('error', translate('Theme not found.'));
        }

        if ($slug === 'default') {
            $request->merge(['section' => 'theme']);

            return $this->saveSimpleThemeSettings($request, $slug);
        }

        $this->themeService->saveThemeSettings($slug, $request->except('_token'));

        return back()->with('success', translate('Theme settings saved.'));
    }

    public function saveSimpleThemeSettings(Request $request, string $slug)
    {
        $this->ensureThemeExists($slug);

        $validated = $request->validate([
            'section' => ['required', 'string', 'in:theme,header,footer,homepage,custom_code,tool_page'],
            'settings' => ['required', 'array'],
            'site_logo_light_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,svg,webp,avif', 'max:5120'],
            'site_logo_dark_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,svg,webp,avif', 'max:5120'],
            'site_favicon_ico_file' => ['nullable', 'file', 'mimes:ico', 'max:2048'],
            'site_favicon_png_file' => ['nullable', 'file', 'mimes:png', 'max:2048'],
            'site_og_image_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
            'bg_image_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
            'payment_icon_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,svg,webp,avif', 'max:4096'],
            'hero_background_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,avif,mp4,webm,ogg', 'max:51200'],
            'hero_split_image_file' => ['nullable', 'file', 'mimes:png,svg', 'max:5120'],
            'brand_logo_*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,svg,webp,avif', 'max:5120'],
            'carousel_item_image_*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,svg,webp,avif', 'max:5120'],
        ]);

        $section = $validated['section'];
        $settings = $validated['settings'];

        if ($section === 'theme') {
            $settings = $this->handleFrontendThemeBackgroundUpload($request, $settings);
            $settings = $this->handleBrandingUploads($request, $settings);
            $this->persistBrandingSettings($settings);
            $this->frontendPresetService->saveThemeSettings($settings);
            Cache::forget('theme-variables-css');
        } elseif ($section === 'header') {
            $this->frontendPresetService->saveHeaderSettings($settings);
        } elseif ($section === 'footer') {
            $settings = $this->handleFooterUploads($request, $settings);
            $this->frontendPresetService->saveFooterSettings($settings);
        } elseif ($section === 'tool_page') {
            $this->frontendPresetService->saveToolPageSettings($settings);
        } elseif ($section === 'homepage') {
            $settings = $this->persistHomepageTemplate($settings);
            $this->frontendPresetService->saveHomepageSettings($settings);

            // Handle dynamic branding logos & carousel file uploads within homepage section configuration
            $homepageConfig = $request->input('homepage_config') ?: $request->input('homepageConfig', []);

            // Multipart uploads send the config as a JSON string so booleans survive encoding.
            if (is_string($homepageConfig)) {
                $homepageConfig = json_decode($homepageConfig, true) ?: [];
            }
            if (! empty($homepageConfig)) {
                $uploadedBrandFiles = [];
                $uploadedCarouselFiles = [];

                // allFiles(), not files->all(): the raw Symfony bag hands back
                // Symfony\...\UploadedFile, which has no store() — only Laravel's subclass does,
                // and allFiles() is what performs that conversion.
                foreach ($request->allFiles() as $key => $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile && str_starts_with($key, 'brand_logo_')) {
                        $uploadedBrandFiles[(int) str_replace('brand_logo_', '', $key)] = $file;
                    }
                    if ($file instanceof \Illuminate\Http\UploadedFile && str_starts_with($key, 'carousel_item_image_')) {
                        $uploadedCarouselFiles[(int) str_replace('carousel_item_image_', '', $key)] = $file;
                    }
                }

                $resolvedConfig = $this->frontendPresetService->getResolvedFrontendHomepageConfig();
                $oldHeroConfig = [];

                foreach ($resolvedConfig['sections'] ?? [] as $sec) {
                    if (($sec['type'] ?? '') === 'hero') {
                        $oldHeroConfig = $sec['config'] ?? [];
                    }
                }

                // Hero images live in the hero section's config, which is what both the admin
                // UI and HeroSection read. Sections are drag-reorderable, so locate it by type
                // rather than trusting the resolved config's index.
                $heroSectionIndex = null;
                foreach ($homepageConfig['sections'] ?? [] as $index => $sec) {
                    if (($sec['type'] ?? '') === 'hero') {
                        $heroSectionIndex = $index;
                        break;
                    }
                }

                if ($heroSectionIndex !== null) {
                    $heroConfig = &$homepageConfig['sections'][$heroSectionIndex]['config'];

                    foreach ([
                        'hero_background_file' => 'hero_background_url',
                        'hero_split_image_file' => 'hero_split_image_url',
                    ] as $fileKey => $configKey) {
                        $oldPath = $oldHeroConfig[$configKey] ?? null;

                        if ($request->hasFile($fileKey)) {
                            $heroConfig[$configKey] = store_public_upload($request->file($fileKey), 'theme/hero', $oldPath);
                        } elseif (($heroConfig[$configKey] ?? '') === '' && $oldPath && Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                    }

                    unset($heroConfig);
                }

                // Brand logos live on the stats_bar section under `brands[].image`, and carousel
                // images on image_carousel under `items[].image_url` — this used to look for
                // sections called 'brands' and 'carousel' with an `items[].image_url` shape that
                // matches neither, so uploads were stored but their paths never persisted.
                $repeaterUploads = [
                    ['type' => 'stats_bar', 'key' => 'brands', 'field' => 'image', 'dir' => 'theme/brands', 'files' => $uploadedBrandFiles],
                    ['type' => 'image_carousel', 'key' => 'items', 'field' => 'image_url', 'dir' => 'theme/carousel', 'files' => $uploadedCarouselFiles],
                ];

                foreach ($repeaterUploads as $spec) {
                    $this->applyRepeaterImageUploads($homepageConfig, $resolvedConfig, $spec);
                }
            }

            if (! empty($homepageConfig)) {
                $this->frontendPresetService->saveHomepageConfig($homepageConfig);
            }
        } elseif ($section === 'custom_code') {
            $this->frontendPresetService->saveCustomCodeSettings($settings);
        }

        Setting::flushCache();

        return back()->with('success', translate('Frontend settings saved.'));
    }

    public function saveHomepageConfig(Request $request, string $slug): RedirectResponse
    {
        $this->ensureThemeExists($slug);

        $config = $request->input('config', []);
        if (is_array($config)) {
            $this->frontendPresetService->saveHomepageConfig($config);
            Setting::flushCache();
        }

        return back()->with('success', translate('Homepage section settings saved.'));
    }

    /**
     * Apply a bundled theme preset. Overwrites the stored settings for whichever
     * sections the preset defines; sections it omits are left untouched.
     */
    public function applyThemePreset(Request $request, string $slug): RedirectResponse
    {
        $this->ensureThemeExists($slug);

        $validated = $request->validate([
            'preset' => ['required', 'string', 'regex:/^[a-z0-9_-]+$/'],
        ]);

        if (! $this->themePresetService->applyPreset($slug, $validated['preset'])) {
            return back()->with('error', translate('That preset could not be applied.'));
        }

        return back()->with('success', translate('Preset applied. Your theme settings were updated.'));
    }

    /**
     * Restore default values for a theme settings section.
     */
    public function restoreThemeDefaults(Request $request, string $slug): RedirectResponse
    {
        $this->ensureThemeExists($slug);

        $validated = $request->validate([
            'section' => ['required', 'string', 'in:theme,header,footer,homepage,custom_code,tool_page'],
        ]);

        $this->frontendPresetService->restoreDefaults($validated['section']);

        Setting::flushCache();

        if ($validated['section'] === 'theme') {
            Cache::forget('theme-variables-css');
        }

        return back()->with('success', translate(':section defaults restored.', [
            'section' => ucfirst(str_replace('_', ' ', $validated['section'])),
        ]));
    }

    private function ensureThemeExists(string $slug): void
    {
        abort_unless($this->themeService->getThemeConfig($slug) !== null, 404);
    }

    /**
     * Store uploaded images for a repeater inside a homepage section's config, and delete the
     * files that are no longer referenced.
     *
     * The section is located by type in the *submitted* config rather than by the resolved
     * config's index: the homepage sections are drag-reorderable, so the two can disagree and
     * the images would attach to whichever section happens to sit at that index.
     *
     * @param  array{type: string, key: string, field: string, dir: string, files: array<int|string, \Illuminate\Http\UploadedFile>}  $spec
     */
    private function applyRepeaterImageUploads(array &$homepageConfig, array $resolvedConfig, array $spec): void
    {
        $sectionIndex = null;

        foreach ($homepageConfig['sections'] ?? [] as $index => $section) {
            if (($section['type'] ?? '') === $spec['type']) {
                $sectionIndex = $index;
                break;
            }
        }

        if ($sectionIndex === null || ! isset($homepageConfig['sections'][$sectionIndex]['config'][$spec['key']])) {
            return;
        }

        $items = &$homepageConfig['sections'][$sectionIndex]['config'][$spec['key']];

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $index => &$item) {
            if (is_array($item) && isset($spec['files'][$index])) {
                $item[$spec['field']] = store_public_upload(
                    $spec['files'][$index],
                    $spec['dir'],
                    $item[$spec['field']] ?? null,
                );
            }
        }
        unset($item);

        $kept = array_filter(array_map(
            fn ($item) => is_array($item) ? ($item[$spec['field']] ?? null) : null,
            $items,
        ));

        foreach ($resolvedConfig['sections'] ?? [] as $section) {
            if (($section['type'] ?? '') !== $spec['type']) {
                continue;
            }

            foreach ($section['config'][$spec['key']] ?? [] as $item) {
                $oldPath = is_array($item) ? ($item[$spec['field']] ?? null) : null;

                if ($oldPath && ! in_array($oldPath, $kept, true) && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
        }
    }

    /**
     * Pull `homepage_template` out of the homepage section and store it as a global
     * setting — it decides whether the theme homepage renders at all, so it cannot live
     * inside the theme's own homepage payload (which saveHomepageSettings() would drop
     * anyway, since the key has no entry in the section's defaults).
     *
     * @return array the settings with the key removed, ready for the section save
     */
    private function persistHomepageTemplate(array $settings): array
    {
        if (! array_key_exists('homepage_template', $settings)) {
            return $settings;
        }

        $template = (string) $settings['homepage_template'];
        unset($settings['homepage_template']);

        // Only 'default' or a currently-offered addon alias. A stale alias — from an
        // addon deactivated between page load and submit — is dropped rather than
        // stored, so the setting can never name a provider that cannot serve `/`.
        if ($template === 'default' || app(HomepageProviderRegistry::class)->resolve($template)) {
            settings_set('homepage_template', $template, 'string', 'general');
        }

        return $settings;
    }

    private function handleFrontendThemeBackgroundUpload(Request $request, array $settings): array
    {
        $storedThemeSettings = $this->frontendPresetService->getStoredThemeSettings();
        $oldBackground = $storedThemeSettings['bg_image'] ?? null;

        if ($request->hasFile('bg_image_file')) {
            $settings['bg_image'] = store_public_upload($request->file('bg_image_file'), 'theme/backgrounds', $oldBackground);
        } elseif (($settings['bg_image'] ?? null) === '') {
            if ($oldBackground && Storage::disk('public')->exists($oldBackground)) {
                Storage::disk('public')->delete($oldBackground);
            }
        }

        return $settings;
    }

    private function handleFooterUploads(Request $request, array $settings): array
    {
        $storedFooterSettings = $this->frontendPresetService->getStoredFooterSettings();
        $oldIcon = $storedFooterSettings['payment_icons'] ?? null;

        if ($request->hasFile('payment_icon_file')) {
            $settings['payment_icons'] = store_public_upload(
                $request->file('payment_icon_file'),
                'theme/footer/payments',
                is_string($oldIcon) ? $oldIcon : null,
            );
        } elseif (($settings['payment_icons'] ?? null) === '') {
            if (is_string($oldIcon) && $oldIcon !== '' && Storage::disk('public')->exists($oldIcon)) {
                Storage::disk('public')->delete($oldIcon);
            }
        }

        return $settings;
    }

    private function handleBrandingUploads(Request $request, array $settings): array
    {
        $brandingFileFields = [
            'site_logo_light_file'  => 'site_logo_light',
            'site_logo_dark_file'   => 'site_logo_dark',
            'site_favicon_ico_file' => 'site_favicon_ico',
            'site_favicon_png_file' => 'site_favicon_png',
            'site_og_image_file'    => 'site_og_image',
        ];

        foreach ($brandingFileFields as $fileField => $settingKey) {
            if ($request->hasFile($fileField)) {
                $targetDirectory = $settingKey === 'site_og_image' ? 'branding/og' : 'branding';
                $settings[$settingKey] = store_public_upload($request->file($fileField), $targetDirectory, settings($settingKey));
            } elseif (($settings[$settingKey] ?? null) === '') {
                $oldPath = settings($settingKey);
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
        }

        return $settings;
    }

    private function persistBrandingSettings(array &$settings): void
    {
        $globalKeys = [
            'site_logo_light',
            'site_logo_dark',
            'site_favicon_ico',
            'site_favicon_png',
            'site_og_image',
        ];

        foreach ($globalKeys as $key) {
            if (array_key_exists($key, $settings)) {
                settings_set($key, $settings[$key], 'string', 'branding');
                unset($settings[$key]);
            }
        }
    }
}
