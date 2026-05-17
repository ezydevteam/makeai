<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AddonService;
use App\Services\ThemeService;
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
            return back()->with('success', "Theme '{$slug}' activated successfully.");
        }

        return back()->with('error', 'Failed to activate theme. Check license requirements.');
    }

    /**
     * Get theme settings page.
     */
    public function themeSettings(string $slug)
    {
        $config = $this->themeService->getThemeConfig($slug);
        if (! $config) {
            return back()->with('error', 'Theme not found.');
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
            return back()->with('error', 'Theme not found.');
        }

        $this->themeService->saveThemeSettings($slug, $request->except('_token'));

        return back()->with('success', 'Theme settings saved.');
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
            return back()->with('success', "Addon '{$slug}' activated successfully.");
        }

        return back()->with('error', 'Failed to activate addon. Check license requirements.');
    }

    /**
     * Deactivate an addon.
     */
    public function deactivateAddon(Request $request, string $slug)
    {
        $this->addonService->deactivate($slug);

        return back()->with('success', "Addon '{$slug}' deactivated.");
    }

    /**
     * Get addon settings page.
     */
    public function addonSettings(string $slug)
    {
        $config = $this->addonService->getAddonConfig($slug);
        if (! $config) {
            return back()->with('error', 'Addon not found.');
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
            return back()->with('error', 'Addon not found.');
        }

        $this->addonService->saveAddonSettings($slug, $request->except('_token'));

        return back()->with('success', 'Addon settings saved.');
    }
}
