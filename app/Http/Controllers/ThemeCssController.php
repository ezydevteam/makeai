<?php

namespace App\Http\Controllers;

use App\Models\AppearanceSetting;
use Illuminate\Support\Facades\Cache;

class ThemeCssController extends Controller
{
    public function __invoke()
    {
        $css = Cache::remember('theme-variables-css', now()->addHours(24), function () {
            $settings = AppearanceSetting::getForScope('theme_default');

            $primary = $settings['primary_color'] ?? '#6366f1';
            $secondary = $settings['secondary_color'] ?? '#6366f1';
            $accent = $settings['accent_color'] ?? '#a855f7';
            $bg = $settings['bg_color'] ?? '#f9fafb';
            $surface = $settings['surface_color'] ?? '#ffffff';
            $textPrimary = $settings['text_primary_color'] ?? '#111827';
            $textSecondary = $settings['text_secondary_color'] ?? '#6b7280';
            $link = $settings['link_color'] ?? $primary;
            $button = $settings['button_color'] ?? $primary;
            $buttonHover = $settings['button_hover_color'] ?? '#4338ca';
            $headerBg = $settings['header_background'] ?? '#ffffff';
            $footerBg = $settings['footer_background'] ?? '#f9fafb';
            $fontBody = $settings['font_body'] ?? 'Inter';
            $fontHeading = $settings['heading_font'] ?? ($settings['font_body'] ?? 'Inter');
            $baseFontSize = $settings['base_font_size'] ?? '16px';
            $headingWeight = $settings['heading_weight'] ?? '700';
            $lineHeight = $settings['line_height'] ?? '1.5';
            $letterSpacing = $settings['letter_spacing'] ?? 'normal';
            $borderRadius = $settings['border_radius'] ?? '12px';
            $containerWidth = $settings['container_width'] ?? '1280px';
            $bgImage = $settings['bg_image'] ?? '';
            $bgSize = $settings['bg_size'] ?? 'cover';
            $bgRepeat = $settings['bg_repeat'] ?? 'no-repeat';
            $bgAttachment = $settings['bg_attachment'] ?? 'scroll';
            $bgPosition = $settings['bg_position'] ?? 'center';
            $bgGradient = $settings['bg_gradient'] ?? '';
            $bgGradientDir = $settings['bg_gradient_direction'] ?? 'to bottom';

            $bodyBg = $bg;
            if ($bgGradient) {
                $bodyBg = "linear-gradient({$bgGradientDir}, {$bg}, {$bgGradient})";
            }

            $lines = [
                ':root {',
                "  --color-primary: {$primary};",
                "  --color-secondary: {$secondary};",
                "  --color-accent: {$accent};",
                "  --color-bg: {$bg};",
                "  --color-surface: {$surface};",
                "  --color-text-primary: {$textPrimary};",
                "  --color-text-secondary: {$textSecondary};",
                "  --color-link: {$link};",
                "  --color-button: {$button};",
                "  --color-button-hover: {$buttonHover};",
                "  --color-header-bg: {$headerBg};",
                "  --color-footer-bg: {$footerBg};",
                "  --font-body: '{$fontBody}', ui-sans-serif, system-ui, sans-serif;",
                "  --font-heading: '{$fontHeading}', ui-sans-serif, system-ui, sans-serif;",
                "  --base-font-size: {$baseFontSize};",
                "  --heading-weight: {$headingWeight};",
                "  --line-height: {$lineHeight};",
                "  --letter-spacing: {$letterSpacing};",
                "  --border-radius: {$borderRadius};",
                "  --container-max-width: {$containerWidth};",
                "}",
                '',
                'body {',
                "  background: {$bodyBg};",
            ];

            if ($bgImage) {
                $lines[] = "  background-image: url('{$bgImage}');";
                $lines[] = "  background-size: {$bgSize};";
                $lines[] = "  background-repeat: {$bgRepeat};";
                $lines[] = "  background-attachment: {$bgAttachment};";
                $lines[] = "  background-position: {$bgPosition};";
            }

            $lines[] = '}';

            return implode("\n", $lines);
        });

        return response($css, 200)
            ->header('Content-Type', 'text/css; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
