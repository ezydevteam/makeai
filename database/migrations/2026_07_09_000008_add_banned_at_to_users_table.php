<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a nullable `banned_at` timestamp to users so admin analytics can measure
 * banning activity over time (there is no status-history table otherwise).
 *
 * Stamped by the User model's `saving` hook whenever `is_banned` flips. Existing
 * banned rows keep a null banned_at and are treated as "banned before the window"
 * by the dashboards, so no backfill is required.
 *
 * Guarded (hasColumn) so it is safe to re-run on installs that already have it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'banned_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('banned_at')->nullable()->after('ban_reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'banned_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('banned_at');
            });
        }
    }
};
