<?php

namespace Resources\Themes\Default\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ThemeSettingsService;
use Illuminate\Support\Str;

class ThemeCssController extends Controller
{
    public function __invoke()
    {
        $presetService = app(ThemeSettingsService::class);
        $settings = $presetService->getResolvedFrontendTheme();
        $customCode = $presetService->getStoredCustomCodeSettings();
        $settings['custom_css'] = $customCode['custom_css'] ?? '';

        $primary = $settings['primary_color'] ?? '#10b981';
        $secondary = $settings['secondary_color'] ?? '#3b82f6';
        $accent = $settings['accent_color'] ?? '#8b5cf6';
        $background = $settings['bg_color'] ?? '#f0fdf8';
        $bgImageEnabled = filter_var($settings['bg_image_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $backgroundImage = $bgImageEnabled ? ($settings['bg_image'] ?? '') : '';
        $fontBody = $settings['font_body'] ?? 'Inter';
        $fontHeading = $settings['heading_font'] ?? 'Plus Jakarta Sans';
        $baseFontSize = $settings['base_font_size'] ?? '15px';
        $headingWeight = $settings['heading_weight'] ?? '700';
        $lineHeight = $settings['line_height'] ?? '1.5';
        $letterSpacing = $this->resolveLetterSpacing($settings['letter_spacing'] ?? 'normal');
        $borderRadius = $settings['border_radius'] ?? '12px';
        $containerWidth = $settings['container_width'] ?? '1280px';

        $headingColor = $settings['heading_color'] ?? '#111827';
        $bodyTextColor = $settings['body_text_color'] ?? '#374151';
        $mutedTextColor = $settings['muted_text_color'] ?? '#6b7280';
        $surface = '#ffffff';
        $border = $settings['border_color'] ?? $this->mixColors($surface, $bodyTextColor, 14);
        $primaryPalette = $this->buildPalette($primary);
        $secondaryPalette = $this->buildPalette($secondary);
        $accentPalette = $this->buildAccentPalette($accent);
        $surfaceOverlay95 = $this->toRgba($surface, 0.95);
        $surfaceOverlay90 = $this->toRgba($surface, 0.90);
        $surfaceOverlay80 = $this->toRgba($surface, 0.80);
        $softPrimary = $this->mixColors($primary, '#ffffff', 88);
        $softSecondary = $this->mixColors($secondary, '#ffffff', 88);
        $softAccent = $this->mixColors($accent, '#ffffff', 88);
        $fontBodyStack = $this->fontStack($fontBody);
        $fontHeadingStack = $this->fontStack($fontHeading, $fontBody);

        $lines = [
            // The custom properties are also exposed on a paint-free class. Content teleported to
            // <body> (the carousel lightbox, modals) sits outside .frontend-theme and would fall
            // back to the static palette from app.css — showing the stock primary rather than the
            // admin's. Such elements opt in with .frontend-theme-vars, which carries the palette
            // without .frontend-theme's !important background/colour, so a dark overlay stays dark.
            '.frontend-theme, .frontend-theme-vars {',
            "  --color-primary: {$primary};",
            "  --color-primary-50: {$primaryPalette[50]};",
            "  --color-primary-100: {$primaryPalette[100]};",
            "  --color-primary-200: {$primaryPalette[200]};",
            "  --color-primary-300: {$primaryPalette[300]};",
            "  --color-primary-400: {$primaryPalette[400]};",
            "  --color-primary-500: {$primaryPalette[500]};",
            "  --color-primary-600: {$primaryPalette[600]};",
            "  --color-primary-700: {$primaryPalette[700]};",
            "  --color-primary-800: {$primaryPalette[800]};",
            "  --color-primary-900: {$primaryPalette[900]};",
            "  --color-primary-950: {$primaryPalette[950]};",
            "  --color-secondary: {$secondary};",
            "  --color-secondary-50: {$secondaryPalette[50]};",
            "  --color-secondary-100: {$secondaryPalette[100]};",
            "  --color-secondary-200: {$secondaryPalette[200]};",
            "  --color-secondary-300: {$secondaryPalette[300]};",
            "  --color-secondary-400: {$secondaryPalette[400]};",
            "  --color-secondary-500: {$secondaryPalette[500]};",
            "  --color-secondary-600: {$secondaryPalette[600]};",
            "  --color-secondary-700: {$secondaryPalette[700]};",
            "  --color-secondary-800: {$secondaryPalette[800]};",
            "  --color-secondary-900: {$secondaryPalette[900]};",
            "  --color-secondary-950: {$secondaryPalette[950]};",
            "  --color-accent: {$accent};",
            "  --color-accent-400: {$accentPalette[400]};",
            "  --color-accent-500: {$accentPalette[500]};",
            "  --color-accent-600: {$accentPalette[600]};",
            "  --color-bg: {$background};",
            "  --color-surface: {$surface};",
            "  --color-heading: {$headingColor};",
            "  --color-text-primary: {$bodyTextColor};",
            "  --color-text-secondary: {$mutedTextColor};",
            "  --color-link: {$secondaryPalette[600]};",
            "  --color-border: {$border};",
            "  --font-body: {$fontBodyStack};",
            "  --font-sans: {$fontBodyStack};",
            "  --font-heading: {$fontHeadingStack};",
            "  --base-font-size: {$baseFontSize};",
            "  --heading-weight: {$headingWeight};",
            "  --line-height: {$lineHeight};",
            "  --letter-spacing: {$letterSpacing};",
            "  --border-radius: {$borderRadius};",
            "  --container-max-width: {$containerWidth};",
            '}',
            '',
            // Paint rules stay on .frontend-theme alone — applying these to teleported overlays
            // would repaint them in the page background and body text colour.
            '.frontend-theme {',
            "  background-color: {$background} !important;",
            "  color: {$bodyTextColor} !important;",
            '  font-family: var(--font-sans);',
            '  font-size: var(--base-font-size);',
            '  line-height: var(--line-height);',
            '  letter-spacing: var(--letter-spacing);',
            '}',
            '',
            '.frontend-theme :where(h1, h2, h3, h4, h5, h6, .font-heading) {',
            '  font-family: var(--font-heading);',
            '  font-weight: var(--heading-weight);',
            '  color: var(--color-heading) !important;',
            '}',
            '',
            '.frontend-theme input:not([type="checkbox"]):not([type="radio"]):not([type="color"]),',
            '.frontend-theme textarea,',
            '.frontend-theme select {',
            '  border-color: var(--color-border);',
            '  color: var(--color-text-primary);',
            '  background-color: var(--color-surface);',
            '}',
            '',
            '.frontend-theme input:not([type="checkbox"]):not([type="radio"]):not([type="color"])::placeholder,',
            '.frontend-theme textarea::placeholder {',
            '  color: var(--color-text-secondary);',
            '}',
            '',
            '.frontend-theme .bg-white,',
            '.frontend-theme .bg-gray-50,',
            '.frontend-theme .bg-gray-100 {',
            '  background-color: var(--color-surface);',
            '}',
            '',
            '.frontend-theme .bg-primary-50 {',
            "  background-color: {$softPrimary};",
            '}',
            '',
            '.frontend-theme .bg-secondary-50 {',
            "  background-color: {$softSecondary};",
            '}',
            '',
            '.frontend-theme .bg-accent-50 {',
            "  background-color: {$softAccent};",
            '}',
            '',
            '.frontend-theme .bg-white\\/95 {',
            "  background-color: {$surfaceOverlay95};",
            '}',
            '',
            '.frontend-theme .bg-white\\/90,',
            '.frontend-theme .bg-gray-50\\/90 {',
            "  background-color: {$surfaceOverlay90};",
            '}',
            '',
            '.frontend-theme .bg-white\\/80 {',
            "  background-color: {$surfaceOverlay80};",
            '}',
            '',
            '.frontend-theme .hover\\:bg-gray-200:hover,',
            '.frontend-theme .bg-gray-200 {',
            "  background-color: {$softPrimary};",
            '}',
            '',
            '.frontend-theme .text-gray-900,',
            '.frontend-theme .text-gray-950,',
            '.frontend-theme .text-slate-900 {',
            '  color: var(--color-heading);',
            '}',
            '',
            '.frontend-theme .text-gray-200,',
            '.frontend-theme .text-gray-300,',
            '.frontend-theme .text-slate-300 {',
            '  color: var(--color-text-secondary);',
            '}',
            '',
            '.frontend-theme .text-gray-700,',
            '.frontend-theme .text-gray-600,',
            '.frontend-theme .text-gray-800,',
            '.frontend-theme .text-slate-800,',
            '.frontend-theme .text-slate-600 {',
            '  color: var(--color-text-primary);',
            '}',
            '',
            '.frontend-theme .text-gray-500,',
            '.frontend-theme .text-gray-400,',
            '.frontend-theme .text-slate-500 {',
            '  color: var(--color-text-secondary);',
            '}',
            '',
            '.frontend-theme .hover\\:text-gray-900:hover,',
            '.frontend-theme .hover\\:text-gray-950:hover {',
            '  color: var(--color-heading);',
            '}',
            '',
            '.frontend-theme .hover\\:text-gray-700:hover,',
            '.frontend-theme .hover\\:text-gray-600:hover {',
            '  color: var(--color-text-primary);',
            '}',
            '',
            '.frontend-theme .placeholder\\:text-gray-400::placeholder,',
            '.frontend-theme .placeholder\\:text-gray-500::placeholder {',
            '  color: var(--color-text-secondary);',
            '}',
            '',
            '.frontend-theme .border-gray-300,',
            '.frontend-theme .border-gray-200,',
            '.frontend-theme .border-gray-100,',
            '.frontend-theme .border-slate-200 {',
            '  border-color: var(--color-border);',
            '}',
            '',
            '.frontend-theme .hover\\:border-gray-300:hover,',
            '.frontend-theme .hover\\:border-gray-200:hover {',
            '  border-color: var(--color-border);',
            '}',
        ];

        if ($backgroundImage !== '') {
            $bgUrl = media_url($backgroundImage);
            $lines[] = '';
            $lines[] = '.frontend-theme {';
            $lines[] = "  background-image: url('{$bgUrl}') !important;";
            $lines[] = '  background-size: cover !important;';
            $lines[] = '  background-repeat: no-repeat !important;';
            $lines[] = '  background-position: center !important;';
            $lines[] = '}';
        }

        $lines[] = '';
        $lines[] = '.frontend-theme .btn-primary {';
        $lines[] = "  background-color: {$primary} !important;";
        $lines[] = "  border-color: {$primary} !important;";
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '.frontend-theme .btn-primary:hover {';
        $lines[] = "  background-color: {$primaryPalette[600]} !important;";
        $lines[] = "  border-color: {$primaryPalette[600]} !important;";
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '.frontend-theme .btn-outline {';
        $lines[] = "  border-color: {$primary} !important;";
        $lines[] = "  color: {$primary} !important;";
        $lines[] = '}';

        $lines[] = '';
        $lines[] = '/* Dark Mode Overrides for Frontend Theme */';
        $lines[] = '.dark .frontend-theme, html.dark .frontend-theme, .dark.frontend-theme, html.dark.frontend-theme {';
        $lines[] = '  --color-bg: #050814 !important;';
        $lines[] = '  --color-surface: #0e1526 !important;';
        $lines[] = '  --color-heading: #ffffff !important;';
        $lines[] = '  --color-text-primary: #f1f5f9 !important;';
        $lines[] = '  --color-text-secondary: #94a3b8 !important;';
        $lines[] = '  --color-border: #19253d !important;';
        $lines[] = '  background-color: #050814 !important;';
        $lines[] = '  background-image: none !important;';
        $lines[] = '  color: #f1f5f9 !important;';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '.dark .frontend-theme .bg-white,';
        $lines[] = '.dark .frontend-theme .bg-gray-50,';
        $lines[] = '.dark .frontend-theme .bg-gray-100,';
        $lines[] = 'html.dark .frontend-theme .bg-white,';
        $lines[] = 'html.dark .frontend-theme .bg-gray-50,';
        $lines[] = 'html.dark .frontend-theme .bg-gray-100 {';
        $lines[] = '  background-color: #0e1526 !important;';
        $lines[] = '  background-image: none !important;';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '.dark .frontend-theme .bg-white\\/90,';
        $lines[] = 'html.dark .frontend-theme .bg-white\\/90 {';
        $lines[] = '  background-color: rgba(14, 21, 38, 0.8) !important;';
        $lines[] = '  background-image: none !important;';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '.dark .frontend-theme .bg-white\\/95,';
        $lines[] = 'html.dark .frontend-theme .bg-white\\/95 {';
        $lines[] = '  background-color: rgba(14, 21, 38, 0.9) !important;';
        $lines[] = '  background-image: none !important;';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '.dark .frontend-theme .text-gray-900,';
        $lines[] = '.dark .frontend-theme .text-gray-950,';
        $lines[] = '.dark .frontend-theme .text-slate-900,';
        $lines[] = 'html.dark .frontend-theme .text-gray-900,';
        $lines[] = 'html.dark .frontend-theme .text-gray-950,';
        $lines[] = 'html.dark .frontend-theme .text-slate-900 {';
        $lines[] = '  color: #ffffff !important;';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '.dark .frontend-theme .text-gray-200,';
        $lines[] = '.dark .frontend-theme .text-gray-300,';
        $lines[] = '.dark .frontend-theme .text-slate-300,';
        $lines[] = '.dark .frontend-theme .text-gray-500,';
        $lines[] = '.dark .frontend-theme .text-gray-400,';
        $lines[] = '.dark .frontend-theme .text-slate-500,';
        $lines[] = 'html.dark .frontend-theme .text-gray-200,';
        $lines[] = 'html.dark .frontend-theme .text-gray-300,';
        $lines[] = 'html.dark .frontend-theme .text-slate-300,';
        $lines[] = 'html.dark .frontend-theme .text-gray-500,';
        $lines[] = 'html.dark .frontend-theme .text-gray-400,';
        $lines[] = 'html.dark .frontend-theme .text-slate-500 {';
        $lines[] = '  color: #94a3b8 !important;';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '.dark .frontend-theme .text-gray-700,';
        $lines[] = '.dark .frontend-theme .text-gray-600,';
        $lines[] = '.dark .frontend-theme .text-gray-800,';
        $lines[] = '.dark .frontend-theme .text-slate-800,';
        $lines[] = '.dark .frontend-theme .text-slate-600,';
        $lines[] = 'html.dark .frontend-theme .text-gray-700,';
        $lines[] = 'html.dark .frontend-theme .text-gray-600,';
        $lines[] = 'html.dark .frontend-theme .text-gray-800,';
        $lines[] = 'html.dark .frontend-theme .text-slate-800,';
        $lines[] = 'html.dark .frontend-theme .text-slate-600 {';
        $lines[] = '  color: #f1f5f9 !important;';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '.dark .frontend-theme .border-gray-300,';
        $lines[] = '.dark .frontend-theme .border-gray-200,';
        $lines[] = '.dark .frontend-theme .border-gray-100,';
        $lines[] = '.dark .frontend-theme .border-slate-200,';
        $lines[] = 'html.dark .frontend-theme .border-gray-300,';
        $lines[] = 'html.dark .frontend-theme .border-gray-200,';
        $lines[] = 'html.dark .frontend-theme .border-gray-100,';
        $lines[] = 'html.dark .frontend-theme .border-slate-200 {';
        $lines[] = '  border-color: #19253d !important;';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '.dark .frontend-theme .bg-primary-50,';
        $lines[] = '.dark .frontend-theme .bg-secondary-50,';
        $lines[] = '.dark .frontend-theme .bg-accent-50,';
        $lines[] = '.dark .frontend-theme .bg-white\\/95,';
        $lines[] = '.dark .frontend-theme .bg-white\\/90,';
        $lines[] = '.dark .frontend-theme .bg-white\\/80,';
        $lines[] = '.dark .frontend-theme .bg-gray-50\\/90,';
        $lines[] = 'html.dark .frontend-theme .bg-primary-50,';
        $lines[] = 'html.dark .frontend-theme .bg-secondary-50,';
        $lines[] = 'html.dark .frontend-theme .bg-accent-50,';
        $lines[] = 'html.dark .frontend-theme .bg-white\\/95,';
        $lines[] = 'html.dark .frontend-theme .bg-white\\/90,';
        $lines[] = 'html.dark .frontend-theme .bg-white\\/80,';
        $lines[] = 'html.dark .frontend-theme .bg-gray-50\\/90 {';
        $lines[] = '  background-image: none !important;';
        $lines[] = '  background-color: rgba(79, 70, 229, 0.15) !important;';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '.dark .frontend-theme input:not([type="checkbox"]):not([type="radio"]):not([type="color"]),';
        $lines[] = '.dark .frontend-theme textarea,';
        $lines[] = '.dark .frontend-theme select,';
        $lines[] = 'html.dark .frontend-theme input:not([type="checkbox"]):not([type="radio"]):not([type="color"]),';
        $lines[] = 'html.dark .frontend-theme textarea,';
        $lines[] = 'html.dark .frontend-theme select {';
        $lines[] = '  background-color: #0e1526 !important;';
        $lines[] = '  color: #f1f5f9 !important;';
        $lines[] = '  border-color: #19253d !important;';
        $lines[] = '}';

        $customCss = $settings['custom_css'] ?? '';
        if ($customCss !== '') {
            $lines[] = '';
            $lines[] = '/* Custom CSS start */';
            $lines[] = $customCss;
            $lines[] = '/* Custom CSS end */';
        }

        return response(implode("\n", $lines), 200)
            ->header('Content-Type', 'text/css; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600, must-revalidate');
    }

    private function buildPalette(string $base): array
    {
        return [
            50 => $this->mixColors($base, '#ffffff', 92),
            100 => $this->mixColors($base, '#ffffff', 82),
            200 => $this->mixColors($base, '#ffffff', 68),
            300 => $this->mixColors($base, '#ffffff', 52),
            400 => $this->mixColors($base, '#ffffff', 28),
            500 => $this->normalizeHex($base),
            600 => $this->mixColors($base, '#000000', 14),
            700 => $this->mixColors($base, '#000000', 26),
            800 => $this->mixColors($base, '#000000', 38),
            900 => $this->mixColors($base, '#000000', 50),
            950 => $this->mixColors($base, '#000000', 64),
        ];
    }

    private function buildAccentPalette(string $base): array
    {
        return [
            400 => $this->mixColors($base, '#ffffff', 20),
            500 => $this->normalizeHex($base),
            600 => $this->mixColors($base, '#000000', 14),
        ];
    }

    private function mixColors(string $base, string $target, int $percent): string
    {
        [$red1, $green1, $blue1] = $this->hexToRgb($base);
        [$red2, $green2, $blue2] = $this->hexToRgb($target);
        $ratio = max(0, min(100, $percent)) / 100;

        return $this->rgbToHex(
            (int) round($red1 + (($red2 - $red1) * $ratio)),
            (int) round($green1 + (($green2 - $green1) * $ratio)),
            (int) round($blue1 + (($blue2 - $blue1) * $ratio)),
        );
    }

    private function toRgba(string $color, float $alpha): string
    {
        [$red, $green, $blue] = $this->hexToRgb($color);
        $opacity = max(0, min(1, $alpha));

        return "rgba({$red}, {$green}, {$blue}, {$opacity})";
    }

    private function hexToRgb(string $color): array
    {
        $hex = ltrim($this->normalizeHex($color), '#');

        return [
            hexdec(Str::substr($hex, 0, 2)),
            hexdec(Str::substr($hex, 2, 2)),
            hexdec(Str::substr($hex, 4, 2)),
        ];
    }

    private function rgbToHex(int $red, int $green, int $blue): string
    {
        return sprintf('#%02x%02x%02x', $red, $green, $blue);
    }

    private function normalizeHex(string $color): string
    {
        $hex = trim($color);
        if (! Str::startsWith($hex, '#')) {
            $hex = "#{$hex}";
        }

        if (strlen($hex) === 4) {
            $hex = sprintf(
                '#%s%s%s%s%s%s',
                $hex[1],
                $hex[1],
                $hex[2],
                $hex[2],
                $hex[3],
                $hex[3],
            );
        }

        return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? strtolower($hex) : '#000000';
    }

    private function fontStack(string $primary, ?string $fallback = null): string
    {
        $fonts = array_filter([$primary, $fallback]);
        $segments = [];

        foreach ($fonts as $font) {
            $value = trim($font);
            if ($value === '') {
                continue;
            }

            $segments[] = $this->isGenericFont($value) ? $value : "'{$value}'";
        }

        $segments[] = 'ui-sans-serif';
        $segments[] = 'system-ui';
        $segments[] = 'sans-serif';

        return implode(', ', array_unique($segments));
    }

    private function isGenericFont(string $font): bool
    {
        return in_array(strtolower($font), ['serif', 'sans-serif', 'system-ui', 'ui-sans-serif', 'monospace'], true);
    }

    private function resolveLetterSpacing(string $value): string
    {
        return match ($value) {
            'tighter' => '-0.05em',
            'tight' => '-0.025em',
            'wide' => '0.025em',
            'wider' => '0.05em',
            default => $value,
        };
    }
}
