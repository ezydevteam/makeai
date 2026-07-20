<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('affiliate_programs', function (Blueprint $table) {
            $table->id();
            $table->enum('commission_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('commission_value', 8, 2)->default('20.00');
            $table->enum('commission_on', ['first_purchase', 'all_purchases', 'subscription'])->default('first_purchase');
            $table->integer('cookie_days')->unsigned()->default(30);
            $table->decimal('min_payout', 10, 2)->default('20.00');
            $table->decimal('max_payout', 10, 2)->default('0.00');
            $table->boolean('payouts_enabled')->default(true);
            $table->json('payout_methods')->nullable();
            $table->boolean('auto_approve_commissions')->default(false);
            $table->boolean('referral_credits_enabled')->default(false);
            $table->decimal('referral_credits_amount', 12, 4)->default('0.0000');
            $table->integer('commission_hold_days')->unsigned()->default(14);
            $table->boolean('allow_custom_alias')->default(false);
            $table->json('marketing_banners')->nullable();
            $table->json('promotional_emails')->nullable();
            $table->json('social_posts')->nullable();
            $table->string('terms_page_slug')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_referrals', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('referrer_id')->unsigned();
            $table->bigInteger('referred_id')->unsigned()->nullable();
            $table->string('referral_code', 20);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('landed_at')->nullable()->index();
            $table->timestamp('converted_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('referrer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('referred_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['referrer_id', 'created_at']);
            $table->index(['referred_id', 'converted_at']);
            $table->index('referral_code');
        });

        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('referrer_id')->unsigned();
            $table->bigInteger('referred_id')->unsigned();
            $table->bigInteger('order_id')->unsigned()->nullable();
            $table->decimal('amount', 10, 4);
            $table->enum('status', ['pending', 'approved', 'paid', 'rejected', 'cancelled'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->foreign('referrer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('referred_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('payments')->onDelete('set null');
            $table->index(['referrer_id', 'status']);
            $table->index(['referred_id', 'order_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('affiliate_payouts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['paypal', 'bank_transfer', 'credits']);
            $table->enum('status', ['pending', 'processing', 'paid', 'rejected'])->default('pending');
            $table->json('payout_details')->nullable();
            $table->text('admin_note')->nullable();
            $table->bigInteger('processed_by')->unsigned()->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('processed_by')->references('id')->on('admins')->onDelete('set null');
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_payouts');
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliate_referrals');
        Schema::dropIfExists('affiliate_programs');
    }
};
