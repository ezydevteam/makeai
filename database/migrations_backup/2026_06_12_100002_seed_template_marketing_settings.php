<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            'template_marketing_stages' => ['value' => json_encode([
                ['slug' => 'awareness', 'label' => 'Awareness', 'icon' => 'ti ti-eye'],
                ['slug' => 'consideration', 'label' => 'Consideration', 'icon' => 'ti ti-bulb'],
                ['slug' => 'conversion', 'label' => 'Conversion', 'icon' => 'ti ti-currency-dollar'],
                ['slug' => 'retention', 'label' => 'Retention', 'icon' => 'ti ti-repeat'],
            ]), 'type' => 'json', 'group' => 'template_marketing'],
            'template_marketing_default_stage' => ['value' => 'awareness', 'type' => 'string', 'group' => 'template_marketing'],
        ];

        foreach ($settings as $key => $config) {
            settings_set($key, $config['value'], $config['type'], $config['group']);
        }
    }

    public function down(): void
    {
        \DB::table('settings')
            ->whereIn('key', ['template_marketing_stages', 'template_marketing_default_stage'])
            ->delete();
    }
};
