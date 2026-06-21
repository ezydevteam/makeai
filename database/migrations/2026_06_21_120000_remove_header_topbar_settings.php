<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $setting = DB::table('settings')
            ->where('key', 'frontend_header_settings')
            ->first();

        if (! $setting || ! is_string($setting->value) || $setting->value === '') {
            return;
        }

        $decoded = json_decode($setting->value, true);

        if (! is_array($decoded)) {
            return;
        }

        unset($decoded['topbar']);

        if (is_array($decoded['desktop'] ?? null)) {
            unset($decoded['desktop']['show_topbar']);
        }

        DB::table('settings')
            ->where('key', 'frontend_header_settings')
            ->update([
                'value' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Topbar removal is intentionally irreversible.
    }
};
