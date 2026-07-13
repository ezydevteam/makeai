<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 — automatic GeoIP price localization. When ON (default), visitors see
 * prices converted to their local currency for display; billing still happens in
 * the store base currency (or an explicit per-country price). Buyers who want a
 * single fixed display currency can turn it off.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        settings_set('pricing_auto_localize', true, 'boolean', 'pricing');
    }

    public function down(): void
    {
        //
    }
};
