<?php

namespace App\View\Composers;

use App\Models\Setting;
use App\Services\ThemeSettingsService;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * AppHeadComposer — prepares the shell-only data the root `app` layout needs to
 * render its <head> and Vite entry points.
 *
 * This lives in a composer (not in HandleInertiaRequests' shared props) on
 * purpose: font URLs, theme timestamps and Vite entries are needed ONLY by the
 * initial full-page HTML render. Putting them in Inertia shared props would ship
 * them as dead weight in every XHR partial-reload payload. They are a layout
 * concern, so the layout's composer is their home — and unlike inline @php in the
 * Blade file, the derivation here is unit-testable.
 *
 * Per-page SEO (title/meta/OG/schema) is intentionally NOT here: it comes from
 * each controller as the `seo` page prop and the Blade reads props.seo directly.
 */
class AppHeadComposer
{
    /** System font stacks that must never be requested from the web-font CDN. */
    private const SYSTEM_FONTS = ['system-ui', 'Arial', 'Georgia', 'serif', 'sans-serif'];

    public function __construct(private ThemeSettingsService $themeSettings) {}

    public function compose(View $view): void
    {
        // The Inertia root view is rendered with a `page` payload; read it here so
        // RTL + the active page component are available without inline Blade logic.
        $page = $view->getData()['page'] ?? [];

        $themeSettings = $this->themeSettings->getResolvedFrontendTheme();

        $view->with([
            'isRtl' => in_array(data_get($page, 'props.locale.is_rtl'), [true, 1, '1'], true),
            'themeSettings' => $themeSettings,
            'customCodeSettings' => $this->themeSettings->getStoredCustomCodeSettings(),
            'themeCssTimestamp' => $this->themeCssTimestamp(),
            'fontFamilies' => $this->fontFamilies($themeSettings),
            'viteEntries' => $this->viteEntries($page),
        ]);
    }

    /**
     * Cache-busting stamp for the dynamic /css/theme-variables.css route — the
     * newest change to either the theme settings or the custom code, so the
     * stylesheet URL only changes when the compiled palette actually changes.
     */
    private function themeCssTimestamp(): int
    {
        try {
            return Setting::query()
                ->whereIn('key', ['frontend_theme_settings', 'frontend_custom_code'])
                ->max('updated_at')?->timestamp ?? time();
        } catch (\Throwable) {
            // Unlike every other lookup in this composer, which degrades to
            // defaults via settings(), this one hits the database directly — so
            // before the settings table exists it takes the whole page down. The
            // installation wizard renders through this same root view, which made
            // a freshly extracted package fail with a 500 instead of installing.
            //
            // The value only cache-busts the theme stylesheet, so falling back to
            // "now" costs a redundant CSS fetch and nothing else.
            return time();
        }
    }

    /**
     * Build the Bunny Fonts `family=` query for the admin-selected body/heading
     * fonts (plus the Inter / Plus Jakarta Sans defaults), dropping system stacks
     * that must not be fetched remotely. Returns '' when nothing needs loading.
     */
    private function fontFamilies(array $themeSettings): string
    {
        $selected = array_unique(array_filter([
            $themeSettings['font_body'] ?? 'Inter',
            $themeSettings['heading_font'] ?? 'Plus Jakarta Sans',
            'Inter',
            'Plus Jakarta Sans',
        ]));

        return collect($selected)
            ->reject(fn ($font) => in_array($font, self::SYSTEM_FONTS, true))
            ->map(function ($font) {
                $slug = Str::of($font)
                    ->trim()
                    ->lower()
                    ->replaceMatches('/[^a-z0-9]+/', '-')
                    ->trim('-');

                return "{$slug}:300,400,500,600,700,800";
            })
            ->unique()
            ->implode('|');
    }

    /**
     * Resolve the Vite entry points for the current Inertia page: the shared app
     * bundle always, plus the page's own Vue SFC. Addon pages load only the app
     * bundle; theme pages prefer an override in the active theme, else the core
     * Pages/ component.
     */
    private function viteEntries(array $page): array
    {
        $component = $page['component'] ?? '';

        if ($component && str_starts_with($component, 'Addons/')) {
            return ['resources/js/app.ts'];
        }

        // The installer is a self-contained package under resources/installer,
        // not resources/js/Pages — map its page to that location so @vite loads
        // the right SFC (and its CSS) instead of the old, now-deleted path.
        if ($component && str_starts_with($component, 'Install/')) {
            return ['resources/js/app.ts', 'resources/installer/js/' . substr($component, strlen('Install/')) . '.vue'];
        }

        $activeTheme = settings('active_theme', 'default');
        $themePath = "resources/themes/{$activeTheme}/js/{$component}.vue";

        if ($component && file_exists(base_path($themePath))) {
            return ['resources/js/app.ts', $themePath];
        }

        return ['resources/js/app.ts', "resources/js/Pages/{$component}.vue"];
    }
}
