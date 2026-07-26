<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalises pages.container_width onto the semantic 'default' value.
 *
 * The "Default" width option previously stored the raw CSS length '1280px', while the
 * seeders wrote the string 'default' — which is neither a valid dropdown option nor valid
 * CSS. The theme now resolves 'default' (and empty) to 1280px (Page.vue::pageMaxWidth) and
 * validation accepts 'default', so this collapses existing rows onto that one canonical
 * value. The other explicit widths ('full', '1080px', '1536px') are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        DB::table('pages')
            ->where(function ($q) {
                $q->where('container_width', '1280px')
                    ->orWhereNull('container_width')
                    ->orWhere('container_width', '');
            })
            ->update(['container_width' => 'default']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        // Reverse to the previous raw value so a rollback restores the prior stored form.
        DB::table('pages')
            ->where('container_width', 'default')
            ->update(['container_width' => '1280px']);
    }
};
