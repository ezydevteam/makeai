<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the license server's last SIGNED payload + signature per addon so the
 * authentic `verified_at` can be re-checked offline (tamper-evident anchor for the
 * offline deadline — mirrors the core license's license_signed_payload setting).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addon_licenses', function (Blueprint $table) {
            if (! Schema::hasColumn('addon_licenses', 'signed_payload')) {
                $table->text('signed_payload')->nullable()->after('grace_started_at');
            }
            if (! Schema::hasColumn('addon_licenses', 'signature')) {
                $table->text('signature')->nullable()->after('signed_payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('addon_licenses', function (Blueprint $table) {
            $table->dropColumn(['signed_payload', 'signature']);
        });
    }
};
