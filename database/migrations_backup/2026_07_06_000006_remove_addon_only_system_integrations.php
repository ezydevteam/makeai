<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Core keeps only four system integrations under Extensions (CAPTCHA, spam
 * filter, IP geolocation, currency rates). The publishing/CRM/SMS/push
 * connectors were removed from the catalog (their stub services deleted); they
 * return via addons. Delete their orphaned `external_{slug}_*` settings.
 * Safe no-op on installs that never configured them.
 */
return new class extends Migration
{
    private array $removed = [
        'notion', 'google_drive', 'wordpress', 'zapier', 'slack',
        'canva', 'hubspot', 'airtable', 'sms_provider', 'push_notifications',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach ($this->removed as $slug) {
            DB::table('settings')->where('key', 'like', "external_{$slug}_%")->delete();
        }
    }

    public function down(): void
    {
        // Irreversible: these integrations are now addon-provided.
    }
};
