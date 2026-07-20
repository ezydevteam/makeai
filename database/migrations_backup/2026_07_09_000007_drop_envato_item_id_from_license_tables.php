<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the redundant `envato_item_id` column from addon_licenses and
 * theme_licenses. Premium detection and gating read the item id from the
 * addon/theme MANIFEST (AddonService/ThemeService), never from this license
 * column — it was written on verify but read by nothing.
 *
 * Guarded (hasColumn) so it is safe on installs that already lack the column.
 * Irreversible by design (down() is a no-op): the value is re-fetched from the
 * license server on the next verify if ever needed again.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['addon_licenses', 'theme_licenses'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'envato_item_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('envato_item_id');
                });
            }
        }
    }

    public function down(): void
    {
        // Intentionally irreversible — redundant with the manifest item id.
    }
};
