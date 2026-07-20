<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_programs', function (Blueprint $table) {
            if (! Schema::hasColumn('affiliate_programs', 'payouts_enabled')) {
                $table->boolean('payouts_enabled')->default(true)->after('min_payout');
            }
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_programs', function (Blueprint $table) {
            if (Schema::hasColumn('affiliate_programs', 'payouts_enabled')) {
                $table->dropColumn('payouts_enabled');
            }
        });
    }
};
