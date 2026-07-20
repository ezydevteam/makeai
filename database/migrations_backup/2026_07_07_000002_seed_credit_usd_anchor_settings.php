<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        $creditSettings = [
            'ai_credit_markup' => ['value' => '3', 'type' => 'string', 'group' => 'ai'],
            'ai_credit_min_per_1k' => ['value' => '1', 'type' => 'integer', 'group' => 'ai'],
        ];

        foreach ($creditSettings as $key => $config) {
            settings_set($key, $config['value'], $config['type'], $config['group']);
        }
    }

    public function down(): void
    {
        $keys = [
            'ai_credit_markup',
            'ai_credit_min_per_1k',
        ];

        foreach ($keys as $key) {
            \DB::table('settings')->where('key', $key)->delete();
        }
    }
};
