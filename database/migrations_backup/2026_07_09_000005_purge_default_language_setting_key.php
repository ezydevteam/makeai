<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove the `default_language` settings key. The default language is now
 * tracked ONLY by the languages.is_default column (single source of truth).
 *
 * This key was never seeded — it only existed if an admin saved General
 * Settings, which previously wrote it here AND left languages.is_default
 * untouched, so the two could silently diverge. GeneralSettingsController now
 * maps the picker straight to the column, so this KV row is dead.
 *
 * Irreversible by design (down() is a no-op): the value is fully derivable
 * from languages.is_default, so there is nothing worth restoring.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'default_language')->delete();
    }

    public function down(): void
    {
        // Intentionally irreversible — value now lives in languages.is_default.
    }
};
