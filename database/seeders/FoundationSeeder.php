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
            ['key' => 'site_copyright_text', 'value' => '', 'type' => 'string', 'group' => 'branding'],
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
            ['key' => 'homepage_config', 'value' => json_encode([
                'sections' => [
                    [
                        'id' => 'hero',
                        'type' => 'hero',
                        'enabled' => true,
                        'core' => true,
                        'config' => [
                            'layout' => 'center',
                            'headline' => translate('One Platform. Every AI Tool.'),
                            'subheadline' => translate('Unleash your creativity with powerful AI tools for content, images, chat, and code.'),
                            'primary_cta_text' => translate('Get Started for Free'),
                            'primary_cta_link' => '/register',
                            'primary_cta_style' => 'primary_filled',
                            'primary_cta_icon' => '',
                            'primary_cta_icon_position' => 'left',
                            'secondary_cta_text' => translate('View Pricing'),
                            'secondary_cta_link' => '/pricing',
                            'secondary_cta_style' => 'outline',
                            'secondary_cta_icon' => '',
                            'secondary_cta_icon_position' => 'left',
                            'hero_background_type' => 'image',
                            'hero_background_url' => '',
                            'show_hero_gradient_overlay' => true,
                            'show_stats_separator' => true,
                            'hero_section_height' => 'default',
                            'hero_vertical_padding' => 48,
                            'hero_heading_size' => 'lg',
                            'hero_heading_color' => 'dark',
                            'hero_subheading_color' => 'light',
                            'stats_number_color' => 'dark',
                            'stats_label_color' => 'light',
                            'trust_badge_text' => translate('Trusted by 50,000+ creators'),
                            'stats' => [
                                ['number' => '50K+', 'label' => translate('Users Trusted')],
                                ['number' => '10M+', 'label' => translate('Assets Generated')],
                                ['number' => '99.9%', 'label' => translate('Uptime SLA')],
                            ],
                        ],
                    ],
                    [
                        'id' => 'features',
                        'type' => 'features',
                        'enabled' => true,
                        'core' => true,
                        'config' => [
                            'title' => translate('Supercharge your workflow'),
                            'subtitle' => translate('Everything you need to build the future, powered by AI.'),
                            'layout' => '3-column',
                            'feature_vertical_padding' => 96,
                            'card_style' => 'bordered',
                            'items' => [
                                ['icon' => 'ti ti-pencil', 'title' => translate('AI Writer'), 'description' => translate('Generate blogs, ads, and emails in seconds.'), 'image_url' => '', 'link_url' => '', 'link_open_new_tab' => false],
                                ['icon' => 'ti ti-photo', 'title' => translate('AI Images'), 'description' => translate('Turn prompts into high-resolution visuals.'), 'image_url' => '', 'link_url' => '', 'link_open_new_tab' => false],
                                ['icon' => 'ti ti-code', 'title' => translate('AI Code'), 'description' => translate('Write, refactor, and debug code faster.'), 'image_url' => '', 'link_url' => '', 'link_open_new_tab' => false],
                            ],
                            'heading_color' => 'dark',
                            'subheading_color' => 'light',
                            'learn_more_text' => translate('Learn more'),
                            'button_text' => '',
                            'button_link' => '',
                            'button_style' => 'primary_filled',
                            'button_icon' => '',
                        ],
                    ],
                    [
                        'id' => 'how_it_works',
                        'type' => 'how_it_works',
                        'enabled' => true,
                        'core' => true,
                        'config' => [
                            'heading' => translate('How It Works'),
                            'subheading' => translate('Show each step in a buyer-friendly layout that is easy to edit.'),
                            'icon' => 'ti ti-route',
                            'step_layout' => 'cards',
                            'step_card_style' => 'bordered',
                            'section_vertical_padding' => 96,
                            'items' => [
                                ['title' => translate('Choose a tool'), 'icon' => 'ti ti-click', 'description' => translate('Pick the AI tool that matches your task.'), 'link' => ''],
                                ['title' => translate('Enter your prompt'), 'icon' => 'ti ti-message-2', 'description' => translate('Describe what you want in plain language.'), 'link' => ''],
                                ['title' => translate('Get your result'), 'icon' => 'ti ti-bolt', 'description' => translate('Review, edit, and publish the generated output.'), 'link' => ''],
                            ],
                        ],
                    ],
                    [
                        'id' => 'tools_showcase',
                        'type' => 'tools_showcase',
                        'enabled' => true,
                        'core' => true,
                        'config' => [
                            'title' => translate('AI Tools Showcase'),
                            'subtitle' => translate('Explore the tools buyers can launch immediately after signup.'),
                            'layout' => '3-column',
                            'card_style' => 'bordered',
                            'section_vertical_padding' => 96,
                            'source' => 'all',
                            'max_items' => 6,
                            'heading_color' => 'white',
                            'subheading_color' => 'white',
                            'primary_text' => translate('View all tools'),
                            'primary_link' => '/ai-tools',
                            'primary_icon' => '',
                            'primary_style' => 'primary_filled',
                            'background_style' => 'gradient-1',
                            'background_image_url' => '',
                            'width' => 'contained',
                        ],
                    ],
                    [
                        'id' => 'pricing',
                        'type' => 'pricing',
                        'enabled' => true,
                        'core' => true,
                        'config' => [
                            'heading' => translate('Pricing'),
                            'subheading' => translate('Show your available plans with a clean, buyer-friendly layout.'),
                            'icon' => 'ti ti-credit-card',
                            'source' => 'all',
                        ],
                    ],
                    [
                        'id' => 'cta_banner',
                        'type' => 'cta_banner',
                        'enabled' => true,
                        'core' => false,
                        'config' => [
                            'headline' => translate('Ready to build with AI?'),
                            'subheadline' => translate('Start creating content, images, and code today.'),
                            'primary_text' => translate('Create Account'),
                            'primary_link' => '/register',
                            'primary_icon' => '',
                            'primary_style' => 'primary_filled',
                            'secondary_text' => translate('See Pricing'),
                            'secondary_link' => '/pricing',
                            'secondary_icon' => '',
                            'secondary_style' => 'outline',
                            'background_style' => 'gradient-1',
                            'background_image_url' => '',
                            'width' => 'contained',
                        ],
                    ],
                ],
                'settings' => [
                    'seo' => [
                        'meta_title' => translate(':app — The Ultimate AI Platform', ['app' => translate('Application')]),
                        'meta_description' => translate('Create content, images, chat responses, and code with one powerful AI platform.'),
                        'og_image' => '',
                    ],
                    'scroll_to_top' => [
                        'enabled' => true,
                        'position' => 'right',
                        'show_after_px' => 500,
                    ],
                    'chat_widget_embed' => '',
                ],
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
            ['scope' => 'admin', 'key' => 'sidebar_bg', 'value' => '#ffffff'],
            ['scope' => 'admin', 'key' => 'sidebar_text_color', 'value' => '#ffffff'],
            ['scope' => 'admin', 'key' => 'navbar_bg', 'value' => '#ffffff'],
            ['scope' => 'admin', 'key' => 'navbar_text_color', 'value' => '#111827'],
            ['scope' => 'admin', 'key' => 'accent_color', 'value' => '#a855f7'],
            ['scope' => 'admin', 'key' => 'font_family', 'value' => 'Inter'],
            ['scope' => 'admin', 'key' => 'base_font_size', 'value' => '14px'],
            ['scope' => 'admin', 'key' => 'heading_weight', 'value' => '600'],

            // Frontend theme defaults
            ['scope' => 'theme_default', 'key' => 'primary_color', 'value' => '#6366f1'],
            ['scope' => 'theme_default', 'key' => 'secondary_color', 'value' => '#6366f1'],
            ['scope' => 'theme_default', 'key' => 'accent_color', 'value' => '#a855f7'],
            ['scope' => 'theme_default', 'key' => 'bg_color', 'value' => '#f9fafb'],
            ['scope' => 'theme_default', 'key' => 'surface_color', 'value' => '#ffffff'],
            ['scope' => 'theme_default', 'key' => 'text_primary_color', 'value' => '#111827'],
            ['scope' => 'theme_default', 'key' => 'text_secondary_color', 'value' => '#6b7280'],
            ['scope' => 'theme_default', 'key' => 'link_color', 'value' => '#6366f1'],
            ['scope' => 'theme_default', 'key' => 'button_color', 'value' => '#6366f1'],
            ['scope' => 'theme_default', 'key' => 'button_hover_color', 'value' => '#4338ca'],
            ['scope' => 'theme_default', 'key' => 'header_background', 'value' => '#ffffff'],
            ['scope' => 'theme_default', 'key' => 'footer_background', 'value' => '#f9fafb'],
            ['scope' => 'theme_default', 'key' => 'font_body', 'value' => 'Inter'],
            ['scope' => 'theme_default', 'key' => 'heading_font', 'value' => 'Inter'],
            ['scope' => 'theme_default', 'key' => 'base_font_size', 'value' => '16px'],
            ['scope' => 'theme_default', 'key' => 'heading_weight', 'value' => '700'],
            ['scope' => 'theme_default', 'key' => 'line_height', 'value' => '1.5'],
            ['scope' => 'theme_default', 'key' => 'letter_spacing', 'value' => 'normal'],
            ['scope' => 'theme_default', 'key' => 'border_radius', 'value' => '12px'],
            ['scope' => 'theme_default', 'key' => 'container_width', 'value' => '1280px'],
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
