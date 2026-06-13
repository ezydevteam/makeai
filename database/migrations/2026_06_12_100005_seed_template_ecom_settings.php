<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            'template_ecom_stages' => ['value' => json_encode([
                ['slug' => 'product-listing', 'label' => 'Product Listing', 'icon' => 'ti ti-package'],
                ['slug' => 'email-retention', 'label' => 'Email & Retention', 'icon' => 'ti ti-mail-heart'],
                ['slug' => 'promotions', 'label' => 'Promotions', 'icon' => 'ti ti-discount-2'],
            ]), 'type' => 'json', 'group' => 'template_ecom'],
            'template_ecom_default_stage' => ['value' => 'product-listing', 'type' => 'string', 'group' => 'template_ecom'],
            'template_ecom_show_context_panel' => ['value' => '1', 'type' => 'boolean', 'group' => 'template_ecom'],
            'template_ecom_context_panel_label' => ['value' => 'Your Store Context', 'type' => 'string', 'group' => 'template_ecom'],
        ];

        foreach ($settings as $key => $config) {
            settings_set($key, $config['value'], $config['type'], $config['group']);
        }
    }

    public function down(): void
    {
        \DB::table('settings')
            ->whereIn('key', [
                'template_ecom_stages', 'template_ecom_default_stage',
                'template_ecom_show_context_panel', 'template_ecom_context_panel_label',
            ])
            ->delete();
    }
};
