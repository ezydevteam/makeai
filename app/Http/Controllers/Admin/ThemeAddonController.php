<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppearanceSetting;
use App\Services\AddonLicenseService;
use App\Services\AddonService;
use App\Services\ThemeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Inertia\Inertia;
use ZipArchive;
use Illuminate\Support\Facades\Storage;
use App\Models\Setting;
use App\Http\Controllers\Admin\HeaderBuilderController;
use App\Http\Controllers\Admin\HomepageBuilderController;
use App\Http\Controllers\Admin\FooterBuilderController;

/**
 * Admin Theme & Addon Management Controller.
 * Ref: AI_SaaS_Master_Prompt Part 2.2.
 */
class ThemeAddonController extends Controller
{
    public function __construct(
        private ThemeService $themeService,
        private AddonService $addonService,
        private AddonLicenseService $addonLicenseService,
    ) {}

    // ─── Themes ──────────────────────────────────────────

    /**
     * List all available themes.
     */
    public function themes()
    {
        return Inertia::render('Admin/Themes', [
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
            // Header Builder props
            $headerBuilder = app(HeaderBuilderController::class);
            $headerConfigRaw = Setting::getValue('header_config');
            $headerSavedConfig = $headerConfigRaw ? (is_array($headerConfigRaw) ? $headerConfigRaw : json_decode($headerConfigRaw, true) ?? []) : [];
            $headerSavedConfig = $headerBuilder->migrateLegacyStickyConfig($headerSavedConfig);
            $headerDefaults = $headerBuilder->getDefaults();
            $headerConfig = $headerBuilder->normalizeBlockIds($headerSavedConfig ? array_replace_recursive($headerDefaults, $headerSavedConfig) : $headerDefaults);

            // Homepage Builder props
            $homepageBuilder = app(HomepageBuilderController::class);
            $homepageConfigRaw = Setting::getValue('homepage_config');
            $homepageSavedConfig = is_array($homepageConfigRaw) ? $homepageBuilder->normalizeStoredHomepageConfig($homepageConfigRaw) : null;
            $homepageConfig = is_array($homepageSavedConfig) ? array_replace_recursive($homepageBuilder->getDefaults(), $homepageSavedConfig) : $homepageBuilder->getDefaults();
            $activeHomepageTemplate = settings('homepage_template', 'default');
            $availableTemplates = \App\Models\SiteTemplate::active()
                ->where('slug', 'ai-chatbot')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['slug', 'name', 'requires_pro'])
                ->map(fn ($t) => [
                    'slug' => $t->slug,
                    'name' => $t->name,
                    'requires_pro' => (bool) $t->requires_pro,
                ])
                ->values();
            $gridTemplates = \App\Models\SiteTemplate::active()
                ->where('slug', '!=', 'ai-chatbot')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['slug', 'name', 'requires_pro'])
                ->values();

            // Footer Builder props
            $footerBuilder = app(FooterBuilderController::class);
            $footerConfigRaw = Setting::getValue('footer_config');
            if ($footerConfigRaw) {
                $footerSavedConfig = is_array($footerConfigRaw) ? $footerConfigRaw : json_decode($footerConfigRaw, true) ?? [];
                $footerConfig = $footerBuilder->normalizeConfig($footerSavedConfig);
            } else {
                $footerConfig = $footerBuilder->normalizeConfig($footerBuilder->getDefaults());
            }

            // Shared builders metadata
            $menus = \App\Models\Menu::orderBy('name')->get(['id', 'name', 'slug']);
            $pages = \App\Models\Page::query()
                ->published()
                ->orderBy('title')
                ->get(['id', 'title', 'slug']);
            $aiCategories = \App\Models\Category::active()->aiTools()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'tools_count']);

            return Inertia::render('Admin/Appearance/DefaultThemeSettings', [
                'theme' => $config,
                'settings' => AppearanceSetting::getForScope('theme_default'),
                'headerConfig' => $headerConfig,
                'headerDefaults' => $headerDefaults,
                'homepageConfig' => $homepageConfig,
                'sectionTypes' => HomepageBuilderController::SECTION_TYPES,
                'activeHomepageTemplate' => $activeHomepageTemplate,
                'availableTemplates' => $availableTemplates,
                'gridTemplates' => $gridTemplates,
                'footerConfig' => $footerConfig,
                'menus' => $menus,
                'pages' => $pages,
                'aiCategories' => $aiCategories,
            ]);
        }

        return Inertia::render('Admin/ThemeSettings', [
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
            $validated = $request->validate([
                'settings' => ['required', 'array'],
                'settings.*' => ['nullable', 'max:50000'],
            ]);

            $this->saveAppearanceScopeSettings('theme_default', $validated['settings']);
            Cache::forget('theme-variables-css');

            return back()->with('success', translate('Theme settings saved.'));
        }

        $this->themeService->saveThemeSettings($slug, $request->except('_token'));

        return back()->with('success', translate('Theme settings saved.'));
    }

    private function saveAppearanceScopeSettings(string $scope, array $settings): void
    {
        $colorKeys = [
            'primary_color', 'secondary_color', 'accent_color', 'bg_color',
            'surface_color', 'sidebar_bg', 'sidebar_text_color',
            'navbar_bg', 'navbar_text_color', 'text_primary_color',
            'text_secondary_color', 'link_color', 'button_color',
            'button_hover_color', 'header_background', 'footer_background',
            'bg_gradient',
        ];

        $allowedBorderRadius = ['0px', '8px', '12px', '16px', '20px', '999px'];
        $allowedFontSizes = ['12px', '13px', '14px', '15px', '16px', '18px', '20px'];
        $allowedHeadingWeight = ['400', '500', '600', '700', '800'];
        $allowedLineHeight = ['1.25', '1.375', '1.5', '1.625', '1.75', '2'];
        $allowedLetterSpacing = ['tighter', 'tight', 'normal', 'wide', 'wider'];
        $allowedContainerWidth = ['full', '1080px', '1280px', '1536px'];
        $allowedBgSize = ['cover', 'contain', 'auto'];
        $allowedBgRepeat = ['no-repeat', 'repeat', 'repeat-x', 'repeat-y'];
        $allowedBgAttachment = ['scroll', 'fixed'];
        $allowedBgPosition = ['center', 'top', 'bottom', 'left', 'right', 'top left', 'top right', 'bottom left', 'bottom right'];

        foreach ($settings as $key => $value) {
            if ($value === null || $value === '') {
                AppearanceSetting::where('scope', $scope)->where('key', $key)->delete();
                continue;
            }

            if (in_array($key, $colorKeys, true) && ! preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
                continue;
            }

            if ($key === 'border_radius' && ! in_array($value, $allowedBorderRadius, true)) {
                continue;
            }

            if ($key === 'base_font_size' && ! in_array($value, $allowedFontSizes, true)) {
                continue;
            }

            if ($key === 'heading_weight' && ! in_array($value, $allowedHeadingWeight, true)) {
                continue;
            }

            if ($key === 'line_height' && ! in_array($value, $allowedLineHeight, true)) {
                continue;
            }

            if ($key === 'letter_spacing' && ! in_array($value, $allowedLetterSpacing, true)) {
                continue;
            }

            if ($key === 'container_width' && ! in_array($value, $allowedContainerWidth, true) && ! preg_match('/^\d+px$/', $value)) {
                continue;
            }

            if ($key === 'bg_size' && ! in_array($value, $allowedBgSize, true)) {
                continue;
            }

            if ($key === 'bg_repeat' && ! in_array($value, $allowedBgRepeat, true)) {
                continue;
            }

            if ($key === 'bg_attachment' && ! in_array($value, $allowedBgAttachment, true)) {
                continue;
            }

            if ($key === 'bg_position' && ! in_array($value, $allowedBgPosition, true)) {
                continue;
            }

            AppearanceSetting::updateOrCreate(
                ['scope' => $scope, 'key' => $key],
                ['value' => $value]
            );
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

        return Inertia::render('Admin/Addons', [
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
            // Log activity
            \DB::table('admin_audit_logs')->insert([
                'admin_id' => auth('admin')->id(),
                'action' => 'addon_activated',
                'description' => "Activated addon: {$slug}",
                'metadata' => json_encode(['addon_slug' => $slug]),
                'created_at' => now(),
            ]);

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

        // Log activity
        \DB::table('admin_audit_logs')->insert([
            'admin_id' => auth('admin')->id(),
            'action' => 'addon_deactivated',
            'description' => "Deactivated addon: {$slug}",
            'metadata' => json_encode(['addon_slug' => $slug]),
            'created_at' => now(),
        ]);

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

        // Resolve from either the generic page or the addon-specific page
        return Inertia::render('Addons/' . $slug . '/Admin/Settings', [
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

                \DB::table('admin_audit_logs')->insert([
                    'admin_id' => auth('admin')->id(),
                    'action' => 'addon_activated',
                    'description' => "Activated addon: {$slug}",
                    'metadata' => json_encode(['addon_slug' => $slug]),
                    'created_at' => now(),
                ]);
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

            \DB::table('admin_audit_logs')->insert([
                'admin_id' => auth('admin')->id(),
                'action' => 'addon_deactivated',
                'description' => "Deactivated addon: {$slug}",
                'metadata' => json_encode(['addon_slug' => $slug]),
                'created_at' => now(),
            ]);
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
}
