<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppearanceSetting;
use App\Services\AddonService;
use App\Services\ThemeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Admin Theme & Addon Management Controller.
 * Ref: AI_SaaS_Master_Prompt Part 2.2.
 */
class ThemeAddonController extends Controller
{
    public function __construct(
        private ThemeService $themeService,
        private AddonService $addonService,
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
        return Inertia::render('Admin/Addons', [
            'addons' => $this->addonService->getAvailableAddons(),
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

        return Inertia::render('Admin/AddonSettings', [
            'addon' => $config,
            'settings' => $this->addonService->getAddonSettings($slug),
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
}
