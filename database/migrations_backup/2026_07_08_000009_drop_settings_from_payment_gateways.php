<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the write-only `payment_gateways.settings` JSON column. It was validated,
 * written on every gateway save (always an empty {}), and never read anywhere —
 * gateway config that mattered lived in `credentials`, the fee columns, and the
 * enabled/test-mode flags. Removing it keeps the gateway record honest.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_gateways') && Schema::hasColumn('payment_gateways', 'settings')) {
            Schema::table('payment_gateways', function (Blueprint $table) {
                $table->dropColumn('settings');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_gateways') && ! Schema::hasColumn('payment_gateways', 'settings')) {
            Schema::table('payment_gateways', function (Blueprint $table) {
                $table->json('settings')->nullable();
            });
        }
    }
};
