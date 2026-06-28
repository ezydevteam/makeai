<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AddonLicenseService;
use App\Services\AddonService;
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

/**
 * Admin Theme & Addon Management Controller.
 * Ref: AI_SaaS_Master_Prompt Part 2.2.
 */
class ThemeAddonController extends Controller
{
    public function __construct(
        private ThemeService $themeService,
        private ThemeSettingsService $frontendPresetService,
        private AddonService $addonService,
        private AddonLicenseService $addonLicenseService,
    ) {}

    // ─── Themes ──────────────────────────────────────────

    /**
     * List all available themes.
     */
    public function themes()
    {
        return Inertia::render('Admin/Appearance/Themes', [
            'themes' => $this->themeService->getAvailableThemes(),
            'activeTheme' => $this->themeService->getActiveTheme(),
        ]);
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
                'frontendCustomCodeSettings' => $this->frontendPresetService->getStoredCustomCodeSettings(),
                'menus' => $menus,
                'aiCategories' => Category::aiTools()
                    ->active()
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug']),
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
            'section' => ['required', 'string', 'in:theme,header,footer,homepage,custom_code'],
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
            $settings = $this->handleBrandingUploads($request, $settings);
            $settings = $this->handleFrontendThemeBackgroundUpload($request, $settings);
            $this->persistBrandingSettings($settings);
            $this->frontendPresetService->saveThemeSettings($settings);
            Cache::forget('theme-variables-css');
        } elseif ($section === 'header') {
            $this->frontendPresetService->saveHeaderSettings($settings);
        } elseif ($section === 'footer') {
            $settings = $this->handleFooterUploads($request, $settings);
            $this->frontendPresetService->saveFooterSettings($settings);
        } elseif ($section === 'homepage') {
            $this->frontendPresetService->saveHomepageSettings($settings);
            $homepageConfig = $request->input('homepage_config', []);
            // forceFormData serializes nested objects as JSON strings — decode back to array
            if (is_string($homepageConfig)) {
                $homepageConfig = json_decode($homepageConfig, true) ?? [];
            }

            // Handle hero background file upload
            if ($request->hasFile('hero_background_file')) {
                $path = $request->file('hero_background_file')->store('theme/hero', 'public');
                if (is_array($homepageConfig) && isset($homepageConfig['sections'])) {
                    foreach ($homepageConfig['sections'] as &$section) {
                        if (($section['type'] ?? '') === 'hero') {
                            $section['config']['hero_background_url'] = $path;
                            break;
                        }
                    }
                    unset($section);
                }
            }

            // Handle split-gradient right side image upload
            if ($request->hasFile('hero_split_image_file')) {
                // Delete old file if exists
                if (is_array($homepageConfig) && isset($homepageConfig['sections'])) {
                    foreach ($homepageConfig['sections'] as &$section) {
                        if (($section['type'] ?? '') === 'hero') {
                            $oldPath = $section['config']['hero_split_image_url'] ?? null;
                            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                                Storage::disk('public')->delete($oldPath);
                            }
                            break;
                        }
                    }
                    unset($section);
                }
                $path = $request->file('hero_split_image_file')->store('theme/hero-split', 'public');
                if (is_array($homepageConfig) && isset($homepageConfig['sections'])) {
                    foreach ($homepageConfig['sections'] as &$section) {
                        if (($section['type'] ?? '') === 'hero') {
                            $section['config']['hero_split_image_url'] = $path;
                            break;
                        }
                    }
                    unset($section);
                }
            } elseif (is_array($homepageConfig) && isset($homepageConfig['sections'])) {
                // Handle removal — delete old file when url is cleared
                foreach ($homepageConfig['sections'] as &$section) {
                    if (($section['type'] ?? '') === 'hero') {
                        $currentUrl = $section['config']['hero_split_image_url'] ?? null;
                        if ($currentUrl === '' || $currentUrl === null) {
                            $oldPath = null;
                            // Find old path from stored config
                            $storedConfig = settings('frontend_homepage_config', []);
                            if (is_array($storedConfig) && isset($storedConfig['sections'])) {
                                foreach ($storedConfig['sections'] as $storedSection) {
                                    if (($storedSection['type'] ?? '') === 'hero') {
                                        $oldPath = $storedSection['config']['hero_split_image_url'] ?? null;
                                        break;
                                    }
                                }
                            }
                            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                                Storage::disk('public')->delete($oldPath);
                            }
                        }
                        break;
                    }
                }
                unset($section);
            }

            // Handle brand logos upload for stats_bar section
            $uploadedBrandFiles = [];
            foreach ($request->allFiles() as $key => $file) {
                if (preg_match('/^brand_logo_(\d+)$/', $key, $matches)) {
                    $index = (int)$matches[1];
                    $uploadedBrandFiles[$index] = $file;
                }
            }

            $statsBarSectionIndex = null;
            if (is_array($homepageConfig) && isset($homepageConfig['sections'])) {
                foreach ($homepageConfig['sections'] as $idx => $sec) {
                    if (($sec['type'] ?? '') === 'stats_bar') {
                        $statsBarSectionIndex = $idx;
                        break;
                    }
                }
            }

            if ($statsBarSectionIndex !== null) {
                // Fetch old brands config for file cleanup comparison
                $oldBrands = [];
                $storedConfig = settings('frontend_homepage_config', []);
                if (is_array($storedConfig) && isset($storedConfig['sections'])) {
                    foreach ($storedConfig['sections'] as $storedSection) {
                        if (($storedSection['type'] ?? '') === 'stats_bar') {
                            $oldBrands = $storedSection['config']['brands'] ?? [];
                            break;
                        }
                    }
                }

                $newBrands = &$homepageConfig['sections'][$statsBarSectionIndex]['config']['brands'];
                if (is_array($newBrands)) {
                    foreach ($newBrands as $idx => &$brand) {
                        if (isset($uploadedBrandFiles[$idx])) {
                            // Delete old image if it existed and is replaced
                            $oldImgPath = $brand['image'] ?? null;
                            if ($oldImgPath && Storage::disk('public')->exists($oldImgPath)) {
                                Storage::disk('public')->delete($oldImgPath);
                            }
                            // Store new file
                            $path = $uploadedBrandFiles[$idx]->store('theme/brands', 'public');
                            $brand['image'] = $path;
                        }
                    }
                    unset($brand);
                }

                // Clean up any other orphaned image files
                $oldImages = [];
                foreach ($oldBrands as $b) {
                    if (!empty($b['image'])) {
                        $oldImages[] = $b['image'];
                    }
                }

                $newImages = [];
                if (is_array($newBrands)) {
                    foreach ($newBrands as $b) {
                        if (!empty($b['image'])) {
                            $newImages[] = $b['image'];
                        }
                    }
                }

                foreach ($oldImages as $oldPath) {
                    if (!in_array($oldPath, $newImages)) {
                        if (Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                    }
                }
            }

            // Handle carousel item images upload
            $uploadedCarouselFiles = [];
            foreach ($request->allFiles() as $key => $file) {
                if (preg_match('/^carousel_item_image_(\d+)$/', $key, $matches)) {
                    $index = (int)$matches[1];
                    $uploadedCarouselFiles[$index] = $file;
                }
            }

            $carouselSectionIndex = null;
            if (is_array($homepageConfig) && isset($homepageConfig['sections'])) {
                foreach ($homepageConfig['sections'] as $idx => $sec) {
                    if (($sec['type'] ?? '') === 'image_carousel') {
                        $carouselSectionIndex = $idx;
                        break;
                    }
                }
            }

            if ($carouselSectionIndex !== null) {
                // Fetch old items for file cleanup comparison
                $oldItems = [];
                $storedConfig = settings('frontend_homepage_config', []);
                if (is_array($storedConfig) && isset($storedConfig['sections'])) {
                    foreach ($storedConfig['sections'] as $storedSection) {
                        if (($storedSection['type'] ?? '') === 'image_carousel') {
                            $oldItems = $storedSection['config']['items'] ?? [];
                            break;
                        }
                    }
                }

                $newItems = &$homepageConfig['sections'][$carouselSectionIndex]['config']['items'];
                if (is_array($newItems)) {
                    foreach ($newItems as $idx => &$item) {
                        if (isset($uploadedCarouselFiles[$idx])) {
                            // Delete old image if it existed and is replaced
                            $oldImgPath = $item['image_url'] ?? null;
                            if ($oldImgPath && Storage::disk('public')->exists($oldImgPath)) {
                                Storage::disk('public')->delete($oldImgPath);
                            }
                            // Store new file
                            $path = $uploadedCarouselFiles[$idx]->store('theme/carousel', 'public');
                            $item['image_url'] = $path;
                        }
                    }
                    unset($item);
                }

                // Clean up any other orphaned image files
                $oldImages = [];
                foreach ($oldItems as $item) {
                    if (!empty($item['image_url'])) {
                        $oldImages[] = $item['image_url'];
                    }
                }

                $newImages = [];
                if (is_array($newItems)) {
                    foreach ($newItems as $item) {
                        if (!empty($item['image_url'])) {
                            $newImages[] = $item['image_url'];
                        }
                    }
                }

                foreach ($oldImages as $oldPath) {
                    if (!in_array($oldPath, $newImages)) {
                        if (Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                    }
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
     * Restore default values for a theme settings section.
     */
    public function restoreThemeDefaults(Request $request, string $slug): RedirectResponse
    {
        $this->ensureThemeExists($slug);

        $validated = $request->validate([
            'section' => ['required', 'string', 'in:theme,header,footer,homepage,custom_code'],
        ]);

        $this->frontendPresetService->restoreDefaults($validated['section']);

        Setting::flushCache();

        if ($validated['section'] === 'theme') {
            Cache::forget('theme-variables-css');
        }

        return back()->with('success', translate(':section defaults restored.', [
            'section' => ucfirst($validated['section']),
        ]));
    }

    private function ensureThemeExists(string $slug): void
    {
        abort_unless($this->themeService->getThemeConfig($slug) !== null, 404);
    }

    private function handleFrontendThemeBackgroundUpload(Request $request, array $settings): array
    {
        $storedThemeSettings = $this->frontendPresetService->getStoredThemeSettings();
        $oldBackground = $storedThemeSettings['bg_image'] ?? null;

        if ($request->hasFile('bg_image_file')) {
            if ($oldBackground && Storage::disk('public')->exists($oldBackground)) {
                Storage::disk('public')->delete($oldBackground);
            }

            $settings['bg_image'] = $request->file('bg_image_file')->store('theme/backgrounds', 'public');
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
            if (is_string($oldIcon) && $oldIcon !== '' && Storage::disk('public')->exists($oldIcon)) {
                Storage::disk('public')->delete($oldIcon);
            }

            $settings['payment_icons'] = $request->file('payment_icon_file')->store('theme/footer/payments', 'public');
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
                $oldPath = settings($settingKey);
                if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }

                $targetDirectory = $settingKey === 'site_og_image' ? 'branding/og' : 'branding';
                $settings[$settingKey] = $request->file($fileField)->store($targetDirectory, 'public');
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

    // ─── Addons ──────────────────────────────────────────

    /**
     * List all available addons.
     */
    public function addons()
    {
        $addons = $this->addonService->getAvailableAddons();

        // Enrich with license info for the Vue page
        $addons = array_map(function ($addon) {
            $addon['license'] = $this->addonLicenseService->getLicenseInfo($addon['slug']);
            $addon['envato_item_id'] = $addon['envato_item_id'] ?? null;
            $addon['logo_url'] = ! empty($addon['has_logo'])
                ? route('admin.addons.logo', ['slug' => $addon['slug']])
                : null;
            return $addon;
        }, $addons);

        return Inertia::render('Admin/Appearance/Addons', [
            'addons' => $addons,
        ]);
    }

    /**
     * Serve addon logo from the addon directory when available.
     */
    public function addonLogo(string $slug)
    {
        $config = $this->addonService->getAddonConfig($slug);
        if (! $config) {
            abort(404);
        }

        $logoPath = config('addons.path', base_path('addons')) . '/' . $slug . '/logo.png';

        if (! File::exists($logoPath)) {
            abort(404);
        }

        return response()->file($logoPath, [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Activate an addon.
     */
    public function activateAddon(Request $request, string $slug)
    {
        if ($this->addonService->activate($slug)) {
            $this->logAddonAudit($request, 'addon_activated', $slug);

            return back()->with('success', translate('Addon :addon activated successfully.', ['addon' => $slug]));
        }

        return back()->with('error', translate('Failed to activate addon. Check license requirements.'));
    }

    /**
     * Deactivate an addon.
     */
    public function deactivateAddon(Request $request, string $slug)
    {
        $this->addonService->deactivate($slug);

        $this->logAddonAudit($request, 'addon_deactivated', $slug);

        return back()->with('success', translate('Addon :addon deactivated.', ['addon' => $slug]));
    }

    public function deleteAddon(Request $request, string $slug)
    {
        $config = $this->addonService->getAddonConfig($slug);

        if (! $config) {
            return back()->with('error', translate('Addon not found.'));
        }

        if (! empty($config['is_active'])) {
            return back()->with('error', translate('Deactivate the addon before deleting it.'));
        }

        if (! $this->addonService->delete($slug)) {
            return back()->with('error', translate('Failed to delete addon.'));
        }

        return redirect()->route('admin.addons')->with('success', translate('Addon :addon deleted successfully.', ['addon' => $slug]));
    }

    /**
     * Get addon settings page.
     */
    public function addonSettings(string $slug)
    {
        $config = $this->addonService->getAddonConfig($slug);
        if (! $config) {
            return back()->with('error', translate('Addon not found.'));
        }

        $settings = $this->addonService->getAddonSettings($slug);

        // Fetch available chat models for the AI Assistant model select.
        // Only show models from providers with at least one active, non-disabled API key.
        $configuredProviders = \App\Models\AiKey::available()->pluck('provider');

        $aiModels = \App\Models\AiModel::active()
            ->whereIn('type', ['chat'])
            ->whereIn('provider', $configuredProviders)
            ->orderBy('provider')
            ->orderBy('name')
            ->get(['slug', 'name', 'provider'])
            ->map(fn ($m) => [
                'value' => $m->slug,
                'label' => $m->name,
                'provider' => $m->provider,
            ]);

        $rules = [];
        if ($slug === 'ai-assistant' && \Illuminate\Support\Facades\Schema::hasTable('ai_assistant_rules')) {
            $rules = \Addons\AiAssistant\Models\AiAssistantRule::orderBy('id', 'desc')->get();
        }

        $addonSpecificPage = resource_path('js/Pages/Addons/' . $slug . '/Admin/Settings.vue');
        $page = file_exists($addonSpecificPage)
            ? 'Addons/' . $slug . '/Admin/Settings'
            : 'Admin/Appearance/AddonSettings';

        return Inertia::render($page, [
            'addon' => $config,
            'settings' => $settings,
            'aiModels' => $aiModels,
            'rules' => $rules,
        ]);
    }

    public function saveAddonSettings(Request $request, string $slug)
    {
        $config = $this->addonService->getAddonConfig($slug);
        if (! $config) {
            return back()->with('error', translate('Addon not found.'));
        }

        $data = $request->except('_token');

        // Loop through the fields and intercept file uploads
        foreach ($request->files->keys() as $key) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                if ($file->isValid()) {
                    // Delete the old file if one exists
                    $oldPath = addon_setting($slug, $key);
                    if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }

                    // Store the file and save the relative path
                    $data[$key] = $file->store('assistant', 'public');
                }
            }
        }

        // Also check if any file field was explicitly set to null or cleared or needs clean relative path
        if (!empty($config['settings'])) {
            foreach ($config['settings'] as $setting) {
                if (($setting['type'] ?? '') === 'file' && ! $request->hasFile($setting['key'])) {
                    if ($request->input($setting['key']) === null) {
                        $oldPath = addon_setting($slug, $setting['key']);
                        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                            Storage::disk('public')->delete($oldPath);
                        }
                        $data[$setting['key']] = null;
                    } else {
                        $val = $request->input($setting['key']);
                        if (is_string($val)) {
                            if (str_starts_with($val, '/storage/')) {
                                $val = substr($val, 9);
                            } else {
                                $diskUrl = Storage::disk('public')->url('');
                                if (str_starts_with($val, $diskUrl)) {
                                    $val = str_replace($diskUrl, '', $val);
                                }
                            }
                            $data[$setting['key']] = $val;
                        }
                    }
                }
            }
        }

        $this->addonService->saveAddonSettings($slug, $data);

        return back()->with('success', translate('Addon settings saved.'));
    }

    /**
     * Install addon from uploaded zip.
     */
    public function installAddon(Request $request)
    {
        // CRITICAL SECURITY: Explicit authorization check for addon installation
        abort_unless(auth('admin')->user()?->hasAnyPermission(['addons.manage', 'settings.manage']), 403);

        $request->validate([
            'addon_zip' => ['required', 'file', 'mimes:zip', 'max:20480'],
        ]);

        $file = $request->file('addon_zip');
        $zip = new ZipArchive;
        $openResult = $zip->open($file->getRealPath());

        if ($openResult !== true) {
            return back()->with('error', translate('Failed to open the uploaded zip file.'));
        }

        $addonsPath = config('addons.path', base_path('addons'));

        // Find the root slug directory inside the zip
        $slug = null;
        $rootDirs = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $parts = explode('/', trim($name, '/'));
            if (count($parts) > 0 && $parts[0] !== '' && $parts[0] !== '__MACOSX') {
                $rootDirs[$parts[0]] = true;
            }
        }

        $slug = count($rootDirs) === 1 ? array_key_first($rootDirs) : null;

        if (! $slug) {
            $zip->close();
            return back()->with('error', translate('Invalid addon zip structure. Expected a single root directory.'));
        }

        // CRITICAL SECURITY FIX: Validate all paths to prevent Zip Slip (Path Traversal)
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            
            // 1. Reject absolute paths
            if (str_starts_with($name, '/') || str_starts_with($name, '\\')) {
                $zip->close();
                return back()->with('error', translate('Invalid addon zip: contains absolute paths.'));
            }
            
            // 2. Reject directory traversal attempts
            if (str_contains($name, '../') || str_contains($name, '..\\')) {
                $zip->close();
                return back()->with('error', translate('Invalid addon zip: contains directory traversal attempts.'));
            }
            
            // 3. Ensure the file belongs to the expected root directory
            if (! str_starts_with($name, $slug . '/') && $name !== $slug . '/') {
                $zip->close();
                return back()->with('error', translate('Invalid addon zip: files must be inside the root directory.'));
            }
        }

        // Check if the slug has addon.json or settings.json
        $hasManifest = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, $slug . '/') && basename($name) === 'addon.json') {
                $hasManifest = true;
                break;
            }
            if (str_starts_with($name, $slug . '/') && basename($name) === 'settings.json') {
                $hasManifest = true;
                break;
            }
        }

        if (! $hasManifest) {
            $zip->close();
            return back()->with('error', translate('Invalid addon zip — no addon.json or settings.json found inside.'));
        }

        $destPath = $addonsPath . '/' . $slug;

        // CRITICAL SECURITY FIX: Extract to a secure temporary directory first
        $tempDir = sys_get_temp_dir() . '/addon_upload_' . uniqid();
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zip->extractTo($tempDir);
        $zip->close();

        $sourceDir = $tempDir . '/' . $slug;

        if (! is_dir($sourceDir)) {
            \Illuminate\Support\Facades\File::deleteDirectory($tempDir);
            return back()->with('error', translate('Invalid addon zip structure after extraction.'));
        }

        // Ensure destination parent exists
        if (! is_dir($addonsPath)) {
            mkdir($addonsPath, 0755, true);
        }

        // Copy the validated directory to the final destination safely
        \Illuminate\Support\Facades\File::copyDirectory($sourceDir, $destPath);
        
        // Clean up the temporary directory
        \Illuminate\Support\Facades\File::deleteDirectory($tempDir);

        // Run sync to register the new addon
        $this->addonService->syncFromFilesystem();

        // Run addon migrations
        $this->addonService->migrateAddon($slug);

        return back()->with('success', translate('Addon installed successfully. You can now activate it below.'));
    }

    /**
     * Bulk activate addons.
     */
    public function bulkActivate(Request $request)
    {
        $validated = $request->validate([
            'slugs' => ['required', 'array', 'min:1'],
            'slugs.*' => ['string'],
        ]);

        $activated = 0;
        foreach ($validated['slugs'] as $slug) {
            if ($this->addonService->activate($slug)) {
                $activated++;
                $this->logAddonAudit($request, 'addon_activated', $slug);
            }
        }

        return back()->with('success', translate(':count addon(s) activated.', ['count' => $activated]));
    }

    /**
     * Bulk deactivate addons.
     */
    public function bulkDeactivate(Request $request)
    {
        $validated = $request->validate([
            'slugs' => ['required', 'array', 'min:1'],
            'slugs.*' => ['string'],
        ]);

        $deactivated = 0;
        foreach ($validated['slugs'] as $slug) {
            $this->addonService->deactivate($slug);
            $deactivated++;
            $this->logAddonAudit($request, 'addon_deactivated', $slug);
        }

        return back()->with('success', translate(':count addon(s) deactivated.', ['count' => $deactivated]));
    }

    /**
     * Verify addon license — Envato purchase code validation.
     * Called BEFORE activateAddon for first-time addons.
     */
    public function verifyAddonLicense(Request $request, string $slug)
    {
        $config = $this->addonService->getAddonConfig($slug);
        if (! $config) {
            return response()->json(['error' => translate('Addon not found.')], 404);
        }

        $requiresLicense = $config['requires_license'] ?? false;
        if (! $requiresLicense) {
            return response()->json(['error' => translate('This addon does not require a separate license.')], 422);
        }

        $request->validate([
            'purchase_code' => ['required', 'string', 'max:64'],
        ]);

        $result = $this->addonLicenseService->verify(
            $slug,
            $request->input('purchase_code')
        );

        if (! $result->valid) {
            return response()->json([
                'error' => $result->error,
                'error_code' => $result->errorCode,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'buyer' => $result->buyer,
            'type' => $result->type,
            'message' => translate('License verified for :buyer', ['buyer' => $result->buyer]),
        ]);
    }

    private function logAddonAudit(Request $request, string $action, string $slug): void
    {
        try {
            \DB::table('admin_audit_logs')->insert([
                'admin_id' => auth('admin')->id(),
                'action' => $action,
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => $request->userAgent(),
                'payload' => json_encode([
                    'addon_slug' => $slug,
                ]),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
