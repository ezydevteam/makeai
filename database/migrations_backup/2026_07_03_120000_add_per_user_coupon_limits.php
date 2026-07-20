<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            // How many times a single user may redeem this coupon.
            // NULL = unlimited per user; default 1 = one redemption per user.
            $table->unsignedInteger('per_user_limit')->nullable()->default(1)->after('max_uses');
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // One redemption row per payment keeps recording idempotent.
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['coupon_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn('per_user_limit');
        });
    }
};
