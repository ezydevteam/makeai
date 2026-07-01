<?php

namespace Database\Seeders;

use App\Models\AppearanceSetting;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class FoundationSeeder extends Seeder
{
    /**
     * Seed the foundation data — settings, currencies, and languages.
     */
    public function run(): void
    {
        $this->seedSettings();
        $this->seedAppearanceSettings();
        $this->seedCurrencies();
        $this->seedLanguages();
    }

    private function seedSettings(): void
    {
        $settings = [
            // Branding identity
            ['key' => 'site_name', 'value' => 'MakeAI', 'type' => 'string', 'group' => 'branding'],
            ['key' => 'site_tagline', 'value' => 'One platform. Every AI tool.', 'type' => 'string', 'group' => 'branding'],
            ['key' => 'site_description', 'value' => 'Generate content, images, code, and more with AI.', 'type' => 'string', 'group' => 'branding'],
            ['key' => 'site_logo_light', 'value' => '', 'type' => 'string', 'group' => 'branding'],
            ['key' => 'site_logo_dark', 'value' => '', 'type' => 'string', 'group' => 'branding'],
            ['key' => 'site_favicon_ico', 'value' => '', 'type' => 'string', 'group' => 'branding'],
            ['key' => 'site_favicon_png', 'value' => '', 'type' => 'string', 'group' => 'branding'],
            ['key' => 'site_og_image', 'value' => '', 'type' => 'string', 'group' => 'branding'],
            ['key' => 'site_support_email', 'value' => '', 'type' => 'string', 'group' => 'branding'],
            ['key' => 'site_support_url', 'value' => '', 'type' => 'string', 'group' => 'branding'],
            ['key' => 'site_terms_url', 'value' => '', 'type' => 'string', 'group' => 'branding'],
            ['key' => 'site_privacy_url', 'value' => '', 'type' => 'string', 'group' => 'branding'],

            // General
            ['key' => 'site_url', 'value' => 'http://localhost', 'type' => 'string', 'group' => 'general'],
            ['key' => 'timezone', 'value' => 'UTC', 'type' => 'string', 'group' => 'general'],
            ['key' => 'maintenance_mode', 'value' => '0', 'type' => 'boolean', 'group' => 'general'],
            ['key' => 'app_version', 'value' => '1.0.0', 'type' => 'string', 'group' => 'general'],
            ['key' => 'active_theme', 'value' => 'default', 'type' => 'string', 'group' => 'general'],
            ['key' => 'homepage_template', 'value' => 'default', 'type' => 'string', 'group' => 'general'],
            ['key' => 'frontend_theme_settings', 'value' => json_encode([
                'theme_default_mode' => 'light',
                'theme_allow_user_toggle' => true,
                'page_loading_animation' => 'none',
                'smooth_scroll' => true,
                'show_back_to_top' => true,
                'primary_color' => '#10b981',
                'secondary_color' => '#3b82f6',
                'accent_color' => '#8b5cf6',
                'bg_color' => '#f0fdf8',
                'bg_image' => '',
                'bg_image_enabled' => false,
                'heading_color' => '#111827',
                'body_text_color' => '#374151',
                'muted_text_color' => '#6b7280',
                'border_color' => '#dbe4ea',
                'font_body' => 'Inter',
                'heading_font' => 'Plus Jakarta Sans',
                'base_font_size' => '15px',
                'heading_weight' => '700',
                'line_height' => '1.5',
                'letter_spacing' => 'normal',
                'border_radius' => '12px',
                'container_width' => '1280px',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'type' => 'json', 'group' => 'appearance'],
            ['key' => 'frontend_header_settings', 'value' => json_encode([
                'desktop' => [
                    'layout' => 'classic',
                    'sticky' => true,
                    'show_language_switcher' => true,
                    'show_dark_mode_toggle' => true,
                    'show_cta_button' => true,
                    'cta_text' => translate('Get Started'),
                    'cta_link' => '/register',
                    'menu_source' => 'primary',
                ],
                'mobile_top' => [
                    'enabled' => true,
                    'layout' => 'compact',
                    'height' => 64,
                    'bg_color' => '',
                    'text_color' => '',
                    'show_shadow' => 'none',
                    'sticky_behavior' => 'always',
                    'show_logo' => true,
                    'show_hamburger' => true,
                    'show_dark_mode_toggle' => true,
                ],
                'mobile_bottom' => [
                    'enabled' => false,
                    'hide_menu_labels' => false,
                    'show_glassmorphism' => true,
                    'show_home' => true,
                    'show_search_icon' => false,
                    'show_tools' => true,
                    'show_notification_bell' => false,
                    'show_hamburger' => false,
                    'show_profile' => true,
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'type' => 'json', 'group' => 'appearance'],
            ['key' => 'frontend_footer_settings', 'value' => json_encode([
                'layout' => 'columns',
                'show_newsletter' => false,
                'show_social_icons' => true,
                'show_payment_icons' => true,
                'show_back_to_top' => true,
                'copyright_text' => '',
                'menu_column_1' => 'footer-company',
                'menu_column_2' => 'footer-support',
                'menu_column_3' => 'footer-legal',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'type' => 'json', 'group' => 'appearance'],
            ['key' => 'frontend_homepage_settings', 'value' => json_encode([
                'hero_variant' => 'centered-gradient',
                'show_social_proof' => true,
                'show_features' => true,
                'show_tools' => true,
                'show_steps' => true,
                'show_pricing' => false,
                'show_testimonials' => true,
                'show_faq' => true,
                'show_cta' => true,
                'show_blog' => true,
                'show_newsletter' => true,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'type' => 'json', 'group' => 'appearance'],
            ['key' => 'frontend_custom_code', 'value' => json_encode([
                'custom_css' => '',
                'custom_header_code' => '',
                'custom_footer_code' => '',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'type' => 'json', 'group' => 'appearance'],

            // AI
            ['key' => 'default_ai_provider', 'value' => 'openai', 'type' => 'string', 'group' => 'ai'],
            ['key' => 'default_ai_model', 'value' => 'gpt-4o-mini', 'type' => 'string', 'group' => 'ai'],
            ['key' => 'max_tokens_per_request', 'value' => '4096', 'type' => 'integer', 'group' => 'ai'],
            ['key' => 'daily_token_limit', 'value' => '50000', 'type' => 'integer', 'group' => 'ai'],
            ['key' => 'monthly_token_limit', 'value' => '1000000', 'type' => 'integer', 'group' => 'ai'],
            ['key' => 'global_daily_budget_usd', 'value' => '100', 'type' => 'integer', 'group' => 'ai'],
            ['key' => 'default_credits_new_user', 'value' => '100', 'type' => 'integer', 'group' => 'ai'],

            // License
            ['key' => 'license_purchase_code', 'value' => '', 'type' => 'encrypted', 'group' => 'license'],
            ['key' => 'license_type', 'value' => '1', 'type' => 'integer', 'group' => 'license'],
            ['key' => 'license_buyer', 'value' => '', 'type' => 'string', 'group' => 'license'],
            ['key' => 'license_purchased_at', 'value' => '', 'type' => 'string', 'group' => 'license'],
            ['key' => 'license_supported_until', 'value' => '', 'type' => 'string', 'group' => 'license'],
            ['key' => 'license_verified_at', 'value' => '', 'type' => 'string', 'group' => 'license'],
            ['key' => 'license_domain', 'value' => '', 'type' => 'string', 'group' => 'license'],
            ['key' => 'license_status', 'value' => 'invalid', 'type' => 'string', 'group' => 'license'],
            ['key' => 'license_grace_started_at', 'value' => null, 'type' => 'string', 'group' => 'license'],
            ['key' => 'license_recheck_days', 'value' => '7', 'type' => 'integer', 'group' => 'license'],
            ['key' => 'subscriptions_enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'license'],
            ['key' => 'default_pricing_country', 'value' => 'US', 'type' => 'string', 'group' => 'pricing'],
            ['key' => 'pricing_trusted_proxy_ips', 'value' => '', 'type' => 'string', 'group' => 'pricing'],
            ['key' => 'pricing_show_monthly', 'value' => '1', 'type' => 'boolean', 'group' => 'pricing'],
            ['key' => 'pricing_show_yearly', 'value' => '1', 'type' => 'boolean', 'group' => 'pricing'],
            ['key' => 'pricing_show_lifetime', 'value' => '1', 'type' => 'boolean', 'group' => 'pricing'],
            ['key' => 'pricing_currency_code', 'value' => 'USD', 'type' => 'string', 'group' => 'pricing'],
            ['key' => 'pricing_trial_button_text', 'value' => 'Start Trial', 'type' => 'string', 'group' => 'pricing'],
            ['key' => 'pricing_featured_label_text', 'value' => 'Recommended', 'type' => 'string', 'group' => 'pricing'],
            ['key' => 'pricing_checkout_button_text', 'value' => 'Choose Plan', 'type' => 'string', 'group' => 'pricing'],

            // Mail
            ['key' => 'mail_from_name', 'value' => 'MakeAI', 'type' => 'string', 'group' => 'mail'],
            ['key' => 'mail_from_address', 'value' => 'hello@makeai.com', 'type' => 'string', 'group' => 'mail'],

            // Security
            ['key' => 'login_throttle_max', 'value' => '5', 'type' => 'integer', 'group' => 'security'],
            ['key' => 'login_throttle_minutes', 'value' => '15', 'type' => 'integer', 'group' => 'security'],
            ['key' => 'require_email_verification', 'value' => '1', 'type' => 'boolean', 'group' => 'security'],
            ['key' => 'two_factor_admin', 'value' => '0', 'type' => 'boolean', 'group' => 'security'],

            // Appearance (seeded via AppearanceSetting model, not generic settings)


            // GDPR Cookie Consent
            ['key' => 'gdpr_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'gdpr'],
            ['key' => 'gdpr_eu_only', 'value' => '1', 'type' => 'boolean', 'group' => 'gdpr'],
            ['key' => 'gdpr_banner_position', 'value' => 'bottom', 'type' => 'string', 'group' => 'gdpr'],
            ['key' => 'gdpr_banner_title', 'value' => 'Cookie Preferences', 'type' => 'string', 'group' => 'gdpr'],
            ['key' => 'gdpr_banner_description', 'value' => 'We use cookies to enhance your experience, analyze site usage, and show relevant content.', 'type' => 'string', 'group' => 'gdpr'],
            ['key' => 'gdpr_banner_accept_all_text', 'value' => 'Accept All', 'type' => 'string', 'group' => 'gdpr'],
            ['key' => 'gdpr_banner_customize_text', 'value' => 'Customize', 'type' => 'string', 'group' => 'gdpr'],
            ['key' => 'gdpr_banner_necessary_text', 'value' => 'Necessary Only', 'type' => 'string', 'group' => 'gdpr'],
            ['key' => 'gdpr_banner_save_text', 'value' => 'Save Preferences', 'type' => 'string', 'group' => 'gdpr'],
            ['key' => 'gdpr_banner_bg_color', 'value' => '#ffffff', 'type' => 'string', 'group' => 'gdpr'],
            ['key' => 'gdpr_banner_text_color', 'value' => '#374151', 'type' => 'string', 'group' => 'gdpr'],
            ['key' => 'gdpr_banner_button_color', 'value' => '#4f46e5', 'type' => 'string', 'group' => 'gdpr'],
            ['key' => 'gdpr_banner_button_text_color', 'value' => '#ffffff', 'type' => 'string', 'group' => 'gdpr'],
            ['key' => 'gdpr_show_policy_links', 'value' => '1', 'type' => 'boolean', 'group' => 'gdpr'],
            ['key' => 'gdpr_cookie_policy_url', 'value' => '/cookie-policy', 'type' => 'string', 'group' => 'gdpr'],

            // Social sharing
            ['key' => 'social_share_networks', 'value' => json_encode(['facebook', 'x', 'linkedin', 'whatsapp', 'telegram', 'pinterest', 'reddit', 'email', 'copy']), 'type' => 'json', 'group' => 'social'],
            ['key' => 'social_share_blog_style', 'value' => 'icon-label', 'type' => 'string', 'group' => 'social'],
            ['key' => 'social_share_show_counts', 'value' => '0', 'type' => 'boolean', 'group' => 'social'],
            ['key' => 'social_follow_display_mode', 'value' => 'counts', 'type' => 'string', 'group' => 'social'],
            ['key' => 'social_follow_refresh_hours', 'value' => '24', 'type' => 'integer', 'group' => 'social'],

            // Comments
            ['key' => 'comments_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'comments'],
            ['key' => 'comments_auto_approve_users', 'value' => '1', 'type' => 'boolean', 'group' => 'comments'],
            ['key' => 'comments_allow_guests', 'value' => '0', 'type' => 'boolean', 'group' => 'comments'],
            ['key' => 'comments_require_approval', 'value' => '0', 'type' => 'boolean', 'group' => 'comments'],
            ['key' => 'comments_notify_admin', 'value' => '0', 'type' => 'boolean', 'group' => 'comments'],
            ['key' => 'comments_poll_seconds', 'value' => '60', 'type' => 'integer', 'group' => 'comments'],

            // Rate Limits — text_gen
            ['key' => 'rl_text_gen_guest_max', 'value' => '5', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_text_gen_guest_window', 'value' => '3600', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_text_gen_free_user_max', 'value' => '30', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_text_gen_free_user_window', 'value' => '60', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_text_gen_pro_user_max', 'value' => '120', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_text_gen_pro_user_window', 'value' => '60', 'type' => 'integer', 'group' => 'rate_limits'],

            // Rate Limits — auth
            ['key' => 'rl_auth_guest_max', 'value' => '5', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_auth_guest_window', 'value' => '900', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_auth_free_user_max', 'value' => '10', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_auth_free_user_window', 'value' => '900', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_auth_pro_user_max', 'value' => '20', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_auth_pro_user_window', 'value' => '900', 'type' => 'integer', 'group' => 'rate_limits'],

            // Rate Limits — otp
            ['key' => 'rl_otp_guest_max', 'value' => '5', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_otp_guest_window', 'value' => '900', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_otp_free_user_max', 'value' => '5', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_otp_free_user_window', 'value' => '900', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_otp_pro_user_max', 'value' => '5', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_otp_pro_user_window', 'value' => '900', 'type' => 'integer', 'group' => 'rate_limits'],

            // Rate Limits — contact
            ['key' => 'rl_contact_guest_max', 'value' => '3', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_contact_guest_window', 'value' => '3600', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_contact_free_user_max', 'value' => '5', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_contact_free_user_window', 'value' => '3600', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_contact_pro_user_max', 'value' => '10', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_contact_pro_user_window', 'value' => '3600', 'type' => 'integer', 'group' => 'rate_limits'],

            // Rate Limits — comments
            ['key' => 'rl_comments_guest_max', 'value' => '5', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_comments_guest_window', 'value' => '60', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_comments_free_user_max', 'value' => '10', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_comments_free_user_window', 'value' => '60', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_comments_pro_user_max', 'value' => '20', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_comments_pro_user_window', 'value' => '60', 'type' => 'integer', 'group' => 'rate_limits'],

            // Rate Limits — newsletter
            ['key' => 'rl_newsletter_guest_max', 'value' => '3', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_newsletter_guest_window', 'value' => '3600', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_newsletter_free_user_max', 'value' => '3', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_newsletter_free_user_window', 'value' => '3600', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_newsletter_pro_user_max', 'value' => '3', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_newsletter_pro_user_window', 'value' => '3600', 'type' => 'integer', 'group' => 'rate_limits'],

            // Rate Limits — public
            ['key' => 'rl_public_guest_max', 'value' => '5', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_public_guest_window', 'value' => '3600', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_public_free_user_max', 'value' => '15', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_public_free_user_window', 'value' => '3600', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_public_pro_user_max', 'value' => '30', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_public_pro_user_window', 'value' => '3600', 'type' => 'integer', 'group' => 'rate_limits'],

            // Rate Limits — social_auth
            ['key' => 'rl_social_auth_guest_max', 'value' => '10', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_social_auth_guest_window', 'value' => '300', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_social_auth_free_user_max', 'value' => '10', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_social_auth_free_user_window', 'value' => '300', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_social_auth_pro_user_max', 'value' => '10', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_social_auth_pro_user_window', 'value' => '300', 'type' => 'integer', 'group' => 'rate_limits'],

            // Rate Limits — AI abuse
            ['key' => 'rl_ai_abuse_threshold', 'value' => '100', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_ai_abuse_window', 'value' => '60', 'type' => 'integer', 'group' => 'rate_limits'],
            ['key' => 'rl_ai_abuse_ban_duration', 'value' => '86400', 'type' => 'integer', 'group' => 'rate_limits'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }

    private function seedAppearanceSettings(): void
    {
        $appearance = [
            // Admin panel
            ['scope' => 'admin', 'key' => 'primary_color', 'value' => '#6366f1'],
            ['scope' => 'admin', 'key' => 'secondary_color', 'value' => '#3b82f6'],
            ['scope' => 'admin', 'key' => 'sidebar_bg', 'value' => '#ffffff'],
            ['scope' => 'admin', 'key' => 'sidebar_text_color', 'value' => '#ffffff'],
            ['scope' => 'admin', 'key' => 'navbar_bg', 'value' => '#ffffff'],
            ['scope' => 'admin', 'key' => 'navbar_text_color', 'value' => '#111827'],
            ['scope' => 'admin', 'key' => 'accent_color', 'value' => '#a855f7'],
            ['scope' => 'admin', 'key' => 'font_family', 'value' => 'Inter'],
            ['scope' => 'admin', 'key' => 'base_font_size', 'value' => '14px'],
            ['scope' => 'admin', 'key' => 'heading_weight', 'value' => '600'],

        ];

        foreach ($appearance as $setting) {
            AppearanceSetting::firstOrCreate(
                ['scope' => $setting['scope'], 'key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }

    private function seedCurrencies(): void
    {
        $currencies = [
            ['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'exchange_rate' => 1.000000, 'decimal_places' => 2, 'is_default' => true, 'is_active' => true],
            ['code' => 'EUR', 'symbol' => '€', 'name' => 'Euro', 'exchange_rate' => 0.920000, 'decimal_places' => 2, 'is_default' => false, 'is_active' => true],
            ['code' => 'GBP', 'symbol' => '£', 'name' => 'British Pound', 'exchange_rate' => 0.790000, 'decimal_places' => 2, 'is_default' => false, 'is_active' => true],
            ['code' => 'BDT', 'symbol' => '৳', 'name' => 'Bangladeshi Taka', 'exchange_rate' => 121.500000, 'decimal_places' => 2, 'is_default' => false, 'is_active' => true],
        ];

        foreach ($currencies as $currency) {
            Currency::firstOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }

    private function seedLanguages(): void
    {
        $languages = [
            ['code' => 'en', 'name' => 'English', 'flag' => null, 'is_rtl' => false, 'is_default' => true, 'is_active' => true],
            ['code' => 'bn', 'name' => 'বাংলা', 'flag' => null, 'is_rtl' => false, 'is_default' => false, 'is_active' => true],
            ['code' => 'ar', 'name' => 'العربية', 'flag' => null, 'is_rtl' => true, 'is_default' => false, 'is_active' => true],
            ['code' => 'es', 'name' => 'Español', 'flag' => null, 'is_rtl' => false, 'is_default' => false, 'is_active' => true],
        ];

        foreach ($languages as $language) {
            Language::firstOrCreate(
                ['code' => $language['code']],
                $language
            );
        }
    }
}
