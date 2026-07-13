<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix a gap left by 2026_07_02_120000_normalize_subscription_status_spelling:
 * that migration moved users.subscription_status DATA to the canonical
 * 'cancelled' spelling and updated the billing_subscriptions enum, but never
 * added 'cancelled' to the users.subscription_status enum — which still only
 * allowed 'canceled'. As a result cancelAtPeriodEnd() (cancel + downgrade-to-
 * free) truncates/errors when writing 'cancelled'. Add the value and normalize
 * any rows still on the old spelling.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // SQLite stores enums as free text — nothing to alter.
        }

        DB::statement("ALTER TABLE users MODIFY subscription_status ENUM('active','trialing','past_due','canceled','cancelled','none') NOT NULL DEFAULT 'none'");

        DB::table('users')
            ->where('subscription_status', 'canceled')
            ->update(['subscription_status' => 'cancelled']);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('users')
            ->where('subscription_status', 'cancelled')
            ->update(['subscription_status' => 'canceled']);

        DB::statement("ALTER TABLE users MODIFY subscription_status ENUM('active','trialing','past_due','canceled','none') NOT NULL DEFAULT 'none'");
    }
};
