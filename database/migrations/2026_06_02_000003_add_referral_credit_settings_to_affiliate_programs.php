<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_programs', function (Blueprint $table) {
            if (! Schema::hasColumn('affiliate_programs', 'referral_credits_enabled')) {
                $table->boolean('referral_credits_enabled')->default(false)->after('auto_approve_commissions');
            }

            if (! Schema::hasColumn('affiliate_programs', 'referral_credits_amount')) {
                $table->decimal('referral_credits_amount', 12, 4)->default(0)->after('referral_credits_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_programs', function (Blueprint $table) {
            if (Schema::hasColumn('affiliate_programs', 'referral_credits_amount')) {
                $table->dropColumn('referral_credits_amount');
            }

            if (Schema::hasColumn('affiliate_programs', 'referral_credits_enabled')) {
                $table->dropColumn('referral_credits_enabled');
            }
        });
    }
};
