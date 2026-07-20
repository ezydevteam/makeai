<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_programs', function (Blueprint $table) {
            if (! Schema::hasColumn('affiliate_programs', 'max_payout')) {
                $table->decimal('max_payout', 10, 2)->default(0)->after('min_payout');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'affiliate_banned')) {
                $table->boolean('affiliate_banned')->default(false)->after('referral_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_programs', function (Blueprint $table) {
            if (Schema::hasColumn('affiliate_programs', 'max_payout')) {
                $table->dropColumn('max_payout');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'affiliate_banned')) {
                $table->dropColumn('affiliate_banned');
            }
        });
    }
};
