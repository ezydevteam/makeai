<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Track when a subscription's monthly credit allowance was last granted.
 *
 * Plans advertise "Monthly credits", but the allowance was only granted on activation
 * and on a GATEWAY RENEWAL — so a yearly subscriber received one month's credits per
 * YEAR, and a lifetime buyer received them exactly once. This column anchors a monthly
 * refresh to the subscription's own anniversary (a purchase on the 20th refreshes on
 * the 20th), rather than the calendar month, which would let a buyer on the 31st
 * collect a second allowance the next day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->timestamp('credits_refreshed_at')->nullable()->after('current_period_end');
        });

        // Existing subscriptions: anchor the first refresh to when their current period
        // began, so they become due on their own anniversary rather than all at once.
        DB::table('billing_subscriptions')
            ->whereNull('credits_refreshed_at')
            ->update(['credits_refreshed_at' => DB::raw('COALESCE(current_period_start, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->dropColumn('credits_refreshed_at');
        });
    }
};
