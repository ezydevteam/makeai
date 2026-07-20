<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $setting = DB::table('settings')->where('key', 'frontend_theme_settings')->first();
        if ($setting) {
            $value = json_decode($setting->value, true);
            if (is_array($value) && array_key_exists('border_radius', $value)) {
                unset($value['border_radius']);
                DB::table('settings')
                    ->where('key', 'frontend_theme_settings')
                    ->update(['value' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $setting = DB::table('settings')->where('key', 'frontend_theme_settings')->first();
        if ($setting) {
            $value = json_decode($setting->value, true);
            if (is_array($value) && !array_key_exists('border_radius', $value)) {
                $value['border_radius'] = '12px';
                DB::table('settings')
                    ->where('key', 'frontend_theme_settings')
                    ->update(['value' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
            }
        }
    }
};
