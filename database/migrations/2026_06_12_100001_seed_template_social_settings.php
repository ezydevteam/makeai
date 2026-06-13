<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            'template_social_platforms' => ['value' => json_encode([
                ['slug' => 'instagram', 'label' => 'Instagram', 'icon' => 'ti ti-brand-instagram', 'color_hex' => '#e1306c', 'enabled' => true],
                ['slug' => 'twitter', 'label' => 'Twitter/X', 'icon' => 'ti ti-brand-x', 'color_hex' => '#1da1f2', 'enabled' => true],
                ['slug' => 'linkedin', 'label' => 'LinkedIn', 'icon' => 'ti ti-brand-linkedin', 'color_hex' => '#0a66c2', 'enabled' => true],
                ['slug' => 'tiktok', 'label' => 'TikTok', 'icon' => 'ti ti-brand-tiktok', 'color_hex' => '#000000', 'enabled' => true],
                ['slug' => 'facebook', 'label' => 'Facebook', 'icon' => 'ti ti-brand-facebook', 'color_hex' => '#1877f2', 'enabled' => true],
                ['slug' => 'youtube', 'label' => 'YouTube', 'icon' => 'ti ti-brand-youtube', 'color_hex' => '#ff0000', 'enabled' => true],
            ]), 'type' => 'json', 'group' => 'template_social'],
            'template_social_default_platform' => ['value' => '', 'type' => 'string', 'group' => 'template_social'],
        ];

        foreach ($settings as $key => $config) {
            settings_set($key, $config['value'], $config['type'], $config['group']);
        }
    }

    public function down(): void
    {
        \DB::table('settings')
            ->whereIn('key', ['template_social_platforms', 'template_social_default_platform'])
            ->delete();
    }
};
