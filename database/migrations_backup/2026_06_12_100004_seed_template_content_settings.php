<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            'template_content_types' => ['value' => json_encode([
                ['slug' => 'articles', 'label' => 'Articles', 'icon' => 'ti ti-pencil'],
                ['slug' => 'seo', 'label' => 'SEO', 'icon' => 'ti ti-world-search'],
                ['slug' => 'rewriting', 'label' => 'Rewriting', 'icon' => 'ti ti-refresh'],
                ['slug' => 'social', 'label' => 'Social', 'icon' => 'ti ti-share'],
            ]), 'type' => 'json', 'group' => 'template_content'],
            'template_content_default_type' => ['value' => 'articles', 'type' => 'string', 'group' => 'template_content'],
            'template_content_show_recent_docs' => ['value' => '0', 'type' => 'boolean', 'group' => 'template_content'],
            'template_content_show_recent_for_guests' => ['value' => '0', 'type' => 'boolean', 'group' => 'template_content'],
        ];

        foreach ($settings as $key => $config) {
            settings_set($key, $config['value'], $config['type'], $config['group']);
        }
    }

    public function down(): void
    {
        \DB::table('settings')
            ->whereIn('key', [
                'template_content_types', 'template_content_default_type',
                'template_content_show_recent_docs', 'template_content_show_recent_for_guests',
            ])
            ->delete();
    }
};
