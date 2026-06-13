<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppearanceSetting;
use App\Services\AddonLicenseService;
use App\Services\AddonService;
use App\Services\ThemeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Inertia\Inertia;
use ZipArchive;

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
            return Inertia::render('Admin/Appearance/DefaultThemeSettings', [
                'theme' => $config,
                'settings' => AppearanceSetting::getForScope('theme_default'),
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
                'settings.*' => ['nullable', 'string', 'max:500'],
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
            return $addon;
        }, $addons);

        return Inertia::render('Admin/Addons', [
            'addons' => $addons,
        ]);
    }

    /**
     * Activate an addon.
     */
    public function activateAddon(Request $request, string $slug)
    {
        if ($this->addonService->activate($slug)) {
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

        return back()->with('success', translate('Addon :addon deactivated.', ['addon' => $slug]));
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

        // Resolve from either the generic page or the addon-specific page
        return Inertia::render('Addons/' . $slug . '/Admin/Settings', [
            'addon' => $config,
            'settings' => $settings,
            'aiModels' => $aiModels,
        ]);
    }

    /**
     * Save addon settings.
     */
    public function saveAddonSettings(Request $request, string $slug)
    {
        $config = $this->addonService->getAddonConfig($slug);
        if (! $config) {
            return back()->with('error', translate('Addon not found.'));
        }

        $this->addonService->saveAddonSettings($slug, $request->except('_token'));

        return back()->with('success', translate('Addon settings saved.'));
    }

    /**
     * Install addon from uploaded zip.
     */
    public function installAddon(Request $request)
    {
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

        // Extract: strip the root folder
        $zip->extractTo($addonsPath);
        $zip->close();

        // Run sync to register the new addon
        $this->addonService->syncFromFilesystem();

        // Run addon migrations
        $this->addonService->migrateAddon($slug);

        return back()->with('success', translate('Addon installed successfully. You can now activate it below.'));
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

        $envatoItemId = $config['envato_item_id'] ?? null;
        if (! $envatoItemId) {
            return response()->json(['error' => translate('This addon does not require a separate license.')], 422);
        }

        $request->validate([
            'purchase_code' => ['required', 'string', 'max:64'],
        ]);

        $result = $this->addonLicenseService->verify(
            $slug,
            $request->input('purchase_code'),
            (int) $envatoItemId
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
