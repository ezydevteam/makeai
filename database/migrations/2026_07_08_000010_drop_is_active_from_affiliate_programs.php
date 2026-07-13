<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the dead `affiliate_programs.is_active` column. The affiliate program's master
 * on/off is the `affiliate_enabled` setting (a feature toggle, consistent with every
 * other feature toggle in the settings table, and the only flag AffiliateService::
 * isEnabled() checks). This column was never read, validated, or shown in the admin —
 * a redundant second "is it on?" flag. The base create-migration no longer defines it;
 * this drops it from installs that were migrated before that change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliate_programs') && Schema::hasColumn('affiliate_programs', 'is_active')) {
            Schema::table('affiliate_programs', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('affiliate_programs') && ! Schema::hasColumn('affiliate_programs', 'is_active')) {
            Schema::table('affiliate_programs', function (Blueprint $table) {
                $table->boolean('is_active')->default(false);
            });
        }
    }
};
