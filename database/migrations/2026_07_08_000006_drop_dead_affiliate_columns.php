<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop two dead affiliate columns:
 *  - affiliate_programs.terms  — superseded by terms_page_slug (which points to a
 *    Page); the old longtext was never read, and the admin form only accepts
 *    terms_page_slug.
 *  - affiliate_commissions.notes — declared in $fillable but never written or read.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliate_programs') && Schema::hasColumn('affiliate_programs', 'terms')) {
            Schema::table('affiliate_programs', function (Blueprint $table) {
                $table->dropColumn('terms');
            });
        }

        if (Schema::hasTable('affiliate_commissions') && Schema::hasColumn('affiliate_commissions', 'notes')) {
            Schema::table('affiliate_commissions', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('affiliate_programs') && ! Schema::hasColumn('affiliate_programs', 'terms')) {
            Schema::table('affiliate_programs', function (Blueprint $table) {
                $table->longText('terms')->nullable();
            });
        }

        if (Schema::hasTable('affiliate_commissions') && ! Schema::hasColumn('affiliate_commissions', 'notes')) {
            Schema::table('affiliate_commissions', function (Blueprint $table) {
                $table->text('notes')->nullable();
            });
        }
    }
};
