<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            'template_dev_categories' => ['value' => json_encode([
                ['slug' => 'generate', 'label' => 'Generate', 'icon' => 'ti ti-code-plus'],
                ['slug' => 'debug', 'label' => 'Debug', 'icon' => 'ti ti-bug'],
                ['slug' => 'optimize', 'label' => 'Optimize', 'icon' => 'ti ti-bolt'],
                ['slug' => 'document', 'label' => 'Document', 'icon' => 'ti ti-file-text'],
            ]), 'type' => 'json', 'group' => 'template_developer'],
            'template_dev_default_category' => ['value' => 'generate', 'type' => 'string', 'group' => 'template_developer'],
            'template_dev_languages' => ['value' => json_encode([
                ['slug' => 'python', 'label' => 'Python', 'visible' => true],
                ['slug' => 'javascript', 'label' => 'JavaScript', 'visible' => true],
                ['slug' => 'typescript', 'label' => 'TypeScript', 'visible' => true],
                ['slug' => 'php', 'label' => 'PHP', 'visible' => true],
                ['slug' => 'go', 'label' => 'Go', 'visible' => true],
                ['slug' => 'rust', 'label' => 'Rust', 'visible' => true],
                ['slug' => 'sql', 'label' => 'SQL', 'visible' => true],
                ['slug' => 'bash', 'label' => 'Bash', 'visible' => true],
                ['slug' => 'csharp', 'label' => 'C#', 'visible' => false],
                ['slug' => 'swift', 'label' => 'Swift', 'visible' => false],
            ]), 'type' => 'json', 'group' => 'template_developer'],
        ];

        foreach ($settings as $key => $config) {
            settings_set($key, $config['value'], $config['type'], $config['group']);
        }
    }

    public function down(): void
    {
        \DB::table('settings')
            ->whereIn('key', [
                'template_dev_categories', 'template_dev_default_category', 'template_dev_languages',
            ])
            ->delete();
    }
};
