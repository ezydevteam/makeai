<?php

namespace Database\Seeders;

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
        $this->seedCurrencies();
        $this->seedLanguages();
    }

    private function seedSettings(): void
    {
        $settings = [
            // General
            ['key' => 'site_name', 'value' => 'MakeAI', 'type' => 'string', 'group' => 'general'],
            ['key' => 'site_tagline', 'value' => 'One platform. Every AI tool.', 'type' => 'string', 'group' => 'general'],
            ['key' => 'site_description', 'value' => 'Generate content, images, code, and more with AI.', 'type' => 'string', 'group' => 'general'],
            ['key' => 'timezone', 'value' => 'UTC', 'type' => 'string', 'group' => 'general'],
            ['key' => 'maintenance_mode', 'value' => '0', 'type' => 'boolean', 'group' => 'general'],
            ['key' => 'app_version', 'value' => '1.0.0', 'type' => 'string', 'group' => 'general'],
            ['key' => 'active_theme', 'value' => 'default', 'type' => 'string', 'group' => 'general'],

            // AI
            ['key' => 'default_ai_provider', 'value' => 'openai', 'type' => 'string', 'group' => 'ai'],
            ['key' => 'default_ai_model', 'value' => 'gpt-4o-mini', 'type' => 'string', 'group' => 'ai'],
            ['key' => 'max_tokens_per_request', 'value' => '4096', 'type' => 'integer', 'group' => 'ai'],
            ['key' => 'daily_token_limit', 'value' => '50000', 'type' => 'integer', 'group' => 'ai'],
            ['key' => 'monthly_token_limit', 'value' => '1000000', 'type' => 'integer', 'group' => 'ai'],
            ['key' => 'global_daily_budget_usd', 'value' => '100', 'type' => 'integer', 'group' => 'ai'],
            ['key' => 'default_credits_new_user', 'value' => '100', 'type' => 'integer', 'group' => 'ai'],

            // License
            ['key' => 'license_type', 'value' => '1', 'type' => 'integer', 'group' => 'license'],
            ['key' => 'license_verified', 'value' => '0', 'type' => 'boolean', 'group' => 'license'],
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

            // Appearance
            ['key' => 'primary_color', 'value' => '#6366f1', 'type' => 'string', 'group' => 'appearance'],
            ['key' => 'admin_font', 'value' => 'Inter', 'type' => 'string', 'group' => 'appearance'],

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
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
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
