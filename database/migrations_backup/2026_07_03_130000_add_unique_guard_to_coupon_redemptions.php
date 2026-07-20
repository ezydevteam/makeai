<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_redemptions', function (Blueprint $table) {
            // Holds the user_id ONLY when the coupon is strictly one-per-user
            // (per_user_limit = 1); NULL otherwise. The unique index below then
            // enforces one-redemption-per-user at the database level for
            // single-use coupons, while NULLs (multi-use coupons) never collide.
            // This closes the checkout race that an application-only count check
            // can't, without restricting coupons that allow several uses per user.
            $table->unsignedBigInteger('user_unique_guard')->nullable()->after('user_id');
            $table->unique(['coupon_id', 'user_unique_guard']);
        });
    }

    public function down(): void
    {
        Schema::table('coupon_redemptions', function (Blueprint $table) {
            $table->dropUnique(['coupon_id', 'user_unique_guard']);
            $table->dropColumn('user_unique_guard');
        });
    }
};
