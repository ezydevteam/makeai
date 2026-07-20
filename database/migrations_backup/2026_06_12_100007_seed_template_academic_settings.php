<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            'template_academic_stages' => ['value' => json_encode([
                ['slug' => 'research', 'label' => 'Research', 'icon' => 'ti ti-search'],
                ['slug' => 'outline', 'label' => 'Outline', 'icon' => 'ti ti-list-tree'],
                ['slug' => 'write', 'label' => 'Write', 'icon' => 'ti ti-pencil'],
                ['slug' => 'polish', 'label' => 'Polish', 'icon' => 'ti ti-sparkles'],
            ]), 'type' => 'json', 'group' => 'template_academic'],
            'template_academic_default_stage' => ['value' => 'research', 'type' => 'string', 'group' => 'template_academic'],
            'template_academic_show_context_panel' => ['value' => '1', 'type' => 'boolean', 'group' => 'template_academic'],
            'template_academic_context_panel_label' => ['value' => 'Academic Context', 'type' => 'string', 'group' => 'template_academic'],
            'template_academic_subject_placeholder' => ['value' => 'e.g. "Environmental Science"', 'type' => 'string', 'group' => 'template_academic'],
            'template_academic_levels' => ['value' => json_encode([
                ['label' => 'High School', 'enabled' => true],
                ['label' => 'Undergraduate', 'enabled' => true],
                ['label' => 'Graduate', 'enabled' => true],
                ['label' => 'PhD', 'enabled' => true],
                ['label' => 'Professional', 'enabled' => true],
            ]), 'type' => 'json', 'group' => 'template_academic'],
            'template_academic_default_level' => ['value' => 'Undergraduate', 'type' => 'string', 'group' => 'template_academic'],
            'template_academic_citation_styles' => ['value' => json_encode([
                ['label' => 'APA', 'enabled' => true],
                ['label' => 'MLA', 'enabled' => true],
                ['label' => 'Chicago', 'enabled' => true],
                ['label' => 'Harvard', 'enabled' => true],
                ['label' => 'IEEE', 'enabled' => true],
                ['label' => 'Vancouver', 'enabled' => false],
            ]), 'type' => 'json', 'group' => 'template_academic'],
            'template_academic_default_citation' => ['value' => 'APA', 'type' => 'string', 'group' => 'template_academic'],
        ];

        foreach ($settings as $key => $config) {
            settings_set($key, $config['value'], $config['type'], $config['group']);
        }
    }

    public function down(): void
    {
        \DB::table('settings')
            ->whereIn('key', [
                'template_academic_stages', 'template_academic_default_stage',
                'template_academic_show_context_panel', 'template_academic_context_panel_label',
                'template_academic_subject_placeholder',
                'template_academic_levels', 'template_academic_default_level',
                'template_academic_citation_styles', 'template_academic_default_citation',
            ])
            ->delete();
    }
};
