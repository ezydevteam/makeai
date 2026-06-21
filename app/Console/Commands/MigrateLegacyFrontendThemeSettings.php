<?php

namespace App\Console\Commands;

use App\Models\AppearanceSetting;
use App\Models\Setting;
use App\Services\ThemeSettingsService;
use Illuminate\Console\Command;

class MigrateLegacyFrontendThemeSettings extends Command
{
    protected $signature = 'makeai:migrate-legacy-frontend-theme {--force : Overwrite existing frontend_* settings}';

    protected $description = 'Migrate legacy frontend appearance and builder settings into the new frontend preset settings model';

    public function handle(ThemeSettingsService $frontendPresetService): int
    {
        if (! $this->option('force') && $this->hasExistingFrontendSettings()) {
            $this->error('Existing frontend_* settings found. Use --force to overwrite them.');

            return self::FAILURE;
        }

        $legacyThemeSettings = AppearanceSetting::getForScope('theme_default');
        $legacyHeaderConfig = Setting::getValue('header_config', []);
        $legacyFooterConfig = Setting::getValue('footer_config', []);
        $legacyHomepageConfig = Setting::getValue('homepage_config', []);

        $mappedThemeSettings = $this->mapThemeSettings($legacyThemeSettings);
        $mappedHeaderSettings = $this->mapHeaderSettings(is_array($legacyHeaderConfig) ? $legacyHeaderConfig : []);
        $mappedFooterSettings = $this->mapFooterSettings(is_array($legacyFooterConfig) ? $legacyFooterConfig : []);
        $mappedHomepageSettings = $this->mapHomepageSettings(is_array($legacyHomepageConfig) ? $legacyHomepageConfig : []);

        $frontendPresetService->saveThemeSettings($mappedThemeSettings);
        $frontendPresetService->saveHeaderSettings($mappedHeaderSettings);
        $frontendPresetService->saveFooterSettings($mappedFooterSettings);
        $frontendPresetService->saveHomepageSettings($mappedHomepageSettings);

        $cleanupSummary = $this->cleanupLegacyFrontendSettings();

        Setting::flushCache();

        $this->info('Legacy frontend settings migrated.');
        $this->line('Theme keys migrated: '.count($mappedThemeSettings));
        $this->line('Header sections migrated: '.count(array_filter($mappedHeaderSettings)));
        $this->line('Footer keys migrated: '.count($mappedFooterSettings));
        $this->line('Homepage keys migrated: '.count($mappedHomepageSettings));
        $this->line('Legacy settings removed: '.($cleanupSummary['legacy_settings'] ?? 0));
        $this->line('Legacy appearance rows removed: '.($cleanupSummary['legacy_appearance_rows'] ?? 0));

        return self::SUCCESS;
    }

    private function hasExistingFrontendSettings(): bool
    {
        return settings('frontend_theme_settings') !== null
            || settings('frontend_header_settings') !== null
            || settings('frontend_footer_settings') !== null
            || settings('frontend_homepage_settings') !== null;
    }

    private function mapThemeSettings(array $legacy): array
    {
        $mapped = array_intersect_key($legacy, array_flip([
            'theme_default_mode',
            'theme_allow_user_toggle',
            'page_loading_animation',
            'smooth_scroll',
            'show_back_to_top',
            'primary_color',
            'secondary_color',
            'accent_color',
            'bg_color',
            'bg_image',
            'heading_color',
            'body_text_color',
            'muted_text_color',
            'border_color',
            'gradient_scheme_enabled',
            'gradient_palette',
            'gradient_start_color',
            'gradient_end_color',
            'bg_gradient_direction',
            'font_body',
            'heading_font',
            'base_font_size',
            'heading_weight',
            'line_height',
            'letter_spacing',
            'border_radius',
            'container_width',
        ]));

        if (! isset($mapped['gradient_end_color']) && isset($legacy['bg_gradient'])) {
            $mapped['gradient_end_color'] = $legacy['bg_gradient'];
        }

        return $mapped;
    }

    private function mapHeaderSettings(array $legacy): array
    {
        $topBlocks = is_array($legacy['top']['blocks'] ?? null) ? $legacy['top']['blocks'] : [];
        $mainBlocks = is_array($legacy['main']['blocks'] ?? null) ? $legacy['main']['blocks'] : [];
        $mobileBlocks = is_array($legacy['mobile']['blocks'] ?? null) ? $legacy['mobile']['blocks'] : [];
        $mobileBottomBlocks = is_array($legacy['mobile_bottom']['blocks'] ?? null) ? $legacy['mobile_bottom']['blocks'] : [];

        $ctaBlock = $this->firstBlockOfType($mainBlocks, 'cta_button');
        $navBlock = $this->firstBlockOfType($mainBlocks, 'navigation');

        return [
            'desktop' => [
                'layout' => is_string($legacy['layout'] ?? null) ? $legacy['layout'] : 'classic',
                'sticky' => (bool) ($legacy['main']['sticky'] ?? true),
                'show_language_switcher' => $this->hasBlockType(array_merge($topBlocks, $mainBlocks), 'language_switcher'),
                'show_dark_mode_toggle' => $this->hasBlockType($mainBlocks, 'dark_mode'),
                'show_cta_button' => $ctaBlock !== null,
                'cta_text' => data_get($ctaBlock, 'config.text', 'Get Started'),
                'cta_link' => data_get($ctaBlock, 'config.link', '/register'),
                'menu_source' => data_get($navBlock, 'config.menu_slug', 'primary'),
            ],
            'mobile_top' => [
                'enabled' => (bool) ($legacy['mobile']['enabled'] ?? true),
                'layout' => is_string($legacy['mobile']['layout'] ?? null) ? $legacy['mobile']['layout'] : 'compact',
                'sticky' => (bool) ($legacy['mobile']['sticky'] ?? true),
                'show_logo' => $this->hasBlockType($mobileBlocks, 'logo'),
                'show_hamburger' => $this->hasBlockType($mobileBlocks, 'hamburger'),
                'show_dark_mode_toggle' => $this->hasBlockType($mobileBlocks, 'dark_mode'),
            ],
            'mobile_bottom' => [
                'enabled' => (bool) ($legacy['mobile_bottom']['enabled'] ?? false),
                'layout' => is_string($legacy['mobile_bottom']['layout'] ?? null) ? $legacy['mobile_bottom']['layout'] : 'tabs',
                'show_home' => $this->hasBlockType($mobileBottomBlocks, 'home_link'),
                'show_tools' => $this->hasBlockType($mobileBottomBlocks, 'search_icon'),
                'show_dashboard' => $this->hasBlockType($mobileBottomBlocks, 'user_menu_icon'),
                'show_profile' => $this->hasBlockType($mobileBottomBlocks, 'user_menu_icon'),
            ],
        ];
    }

    private function mapFooterSettings(array $legacy): array
    {
        $columns = is_array($legacy['columns'] ?? null) ? $legacy['columns'] : [];
        $blocks = collect($columns)->flatMap(fn ($column) => is_array($column['blocks'] ?? null) ? $column['blocks'] : [])->values()->all();
        $menuBlocks = array_values(array_filter($blocks, fn ($block) => ($block['type'] ?? null) === 'menu_list'));

        return [
            'layout' => is_string($legacy['layout'] ?? null) ? $legacy['layout'] : 'columns',
            'show_newsletter' => $this->hasBlockType($blocks, 'newsletter'),
            'show_social_icons' => $this->hasBlockType($blocks, 'social_icons'),
            'show_payment_icons' => (bool) data_get($legacy, 'bottom_bar.show_payment_icons', true),
            'show_back_to_top' => (bool) data_get($legacy, 'bottom_bar.show_back_to_top', true),
            'menu_column_1' => data_get($menuBlocks, '0.config.menu_slug', 'footer-company'),
            'menu_column_2' => data_get($menuBlocks, '1.config.menu_slug', 'footer-support'),
            'menu_column_3' => data_get($menuBlocks, '2.config.menu_slug', 'footer-legal'),
        ];
    }

    private function mapHomepageSettings(array $legacy): array
    {
        $sections = is_array($legacy['sections'] ?? null) ? $legacy['sections'] : [];
        $hero = collect($sections)->first(fn ($section) => ($section['type'] ?? null) === 'hero');

        return [
            'hero_variant' => $this->mapHeroVariant(is_array($hero) ? $hero : []),
            'show_social_proof' => $this->hasSectionType($sections, 'stats_bar'),
            'show_features' => $this->hasSectionType($sections, 'features'),
            'show_tools' => $this->hasSectionType($sections, 'all_tools') || $this->hasSectionType($sections, 'template_grid'),
            'show_steps' => $this->hasSectionType($sections, 'how_it_works'),
            'show_pricing' => $this->hasSectionType($sections, 'pricing'),
            'show_testimonials' => $this->hasSectionType($sections, 'testimonials'),
            'show_faq' => $this->hasSectionType($sections, 'faq'),
            'show_cta' => $this->hasSectionType($sections, 'cta_banner'),
            'show_blog' => $this->hasSectionType($sections, 'latest_posts'),
            'show_newsletter' => $this->hasSectionType($sections, 'newsletter'),
        ];
    }

    private function mapHeroVariant(array $hero): string
    {
        $layout = data_get($hero, 'config.layout');
        $backgroundStyle = data_get($hero, 'config.background_style');

        if ($layout === 'centered' && $backgroundStyle === 'gradient') {
            return 'centered-gradient';
        }

        if ($layout === 'split') {
            return 'split-gradient';
        }

        if ($layout === 'showcase') {
            return 'app-showcase';
        }

        return 'centered-gradient';
    }

    private function hasSectionType(array $sections, string $type): bool
    {
        foreach ($sections as $section) {
            if (($section['type'] ?? null) === $type && ($section['enabled'] ?? true)) {
                return true;
            }
        }

        return false;
    }

    private function hasBlockType(array $blocks, string $type): bool
    {
        return $this->firstBlockOfType($blocks, $type) !== null;
    }

    private function firstBlockOfType(array $blocks, string $type): ?array
    {
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === $type && ($block['enabled'] ?? true)) {
                return $block;
            }
        }

        return null;
    }

    private function cleanupLegacyFrontendSettings(): array
    {
        $legacySettingKeys = [
            'header_config',
            'footer_config',
            'homepage_config',
        ];

        $legacySettingsRemoved = Setting::query()
            ->whereIn('key', $legacySettingKeys)
            ->delete();

        $legacyAppearanceRowsRemoved = AppearanceSetting::query()
            ->where('scope', 'theme_default')
            ->delete();

        return [
            'legacy_settings' => $legacySettingsRemoved,
            'legacy_appearance_rows' => $legacyAppearanceRowsRemoved,
        ];
    }
}
