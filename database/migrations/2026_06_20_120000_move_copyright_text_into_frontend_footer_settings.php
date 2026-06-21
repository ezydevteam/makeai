<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacy = DB::table('settings')
            ->whereIn('key', ['site_copyright_text', 'app_copyright_text'])
            ->orderByRaw("CASE WHEN `key` = 'site_copyright_text' THEN 0 ELSE 1 END")
            ->get(['key', 'value']);

        $legacyValue = '';

        foreach ($legacy as $row) {
            if (is_string($row->value) && trim($row->value) !== '') {
                $legacyValue = $row->value;
                break;
            }
        }

        $footerSetting = DB::table('settings')
            ->where('key', 'frontend_footer_settings')
            ->first(['value']);

        $footerSettings = [];

        if (is_string($footerSetting?->value) && $footerSetting->value !== '') {
            $decoded = json_decode($footerSetting->value, true);
            if (is_array($decoded)) {
                $footerSettings = $decoded;
            }
        }

        if (! array_key_exists('copyright_text', $footerSettings) || $footerSettings['copyright_text'] === null || $footerSettings['copyright_text'] === '') {
            $footerSettings['copyright_text'] = $legacyValue;
        }

        DB::table('settings')->updateOrInsert(
            ['key' => 'frontend_footer_settings'],
            [
                'value' => json_encode($footerSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'type' => 'json',
                'group' => 'appearance',
            ]
        );

        DB::table('settings')
            ->whereIn('key', ['site_copyright_text', 'app_copyright_text'])
            ->delete();
    }

    public function down(): void
    {
        $footerSetting = DB::table('settings')
            ->where('key', 'frontend_footer_settings')
            ->first(['value']);

        $footerSettings = [];

        if (is_string($footerSetting?->value) && $footerSetting->value !== '') {
            $decoded = json_decode($footerSetting->value, true);
            if (is_array($decoded)) {
                $footerSettings = $decoded;
            }
        }

        $copyrightText = is_string($footerSettings['copyright_text'] ?? null)
            ? $footerSettings['copyright_text']
            : '';

        DB::table('settings')->updateOrInsert(
            ['key' => 'site_copyright_text'],
            [
                'value' => $copyrightText,
                'type' => 'string',
                'group' => 'branding',
            ]
        );

        unset($footerSettings['copyright_text']);

        DB::table('settings')
            ->where('key', 'frontend_footer_settings')
            ->update([
                'value' => json_encode($footerSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'type' => 'json',
                'group' => 'appearance',
            ]);
    }
};
