<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the coupon `is_recurring` flag.
 *
 * It could never take effect: applying a coupon forces the purchase to a one-time
 * charge (a recurring subscription bills the fixed price configured in Stripe/PayPal
 * and cannot carry an arbitrary discount), so no gateway subscription ever carried a
 * coupon and the renewal-time discount that read this flag was unreachable. The admin
 * UI nonetheless exposed the toggle and a "Recurring Coupons" stat, promising a
 * behaviour the system never had.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('coupons', 'is_recurring')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('is_recurring');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('coupons', 'is_recurring')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('used_count');
        });
    }
};
