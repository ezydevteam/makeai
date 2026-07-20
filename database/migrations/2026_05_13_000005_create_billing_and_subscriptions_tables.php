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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('bottom_info_text')->nullable();
            $table->decimal('price_monthly', 10, 2)->default('0.00');
            $table->decimal('price_yearly', 10, 2)->default('0.00');
            $table->decimal('price_lifetime', 10, 2)->nullable();
            $table->decimal('original_price_monthly', 12, 2)->nullable();
            $table->decimal('original_price_yearly', 12, 2)->nullable();
            $table->decimal('original_price_lifetime', 12, 2)->nullable();
            $table->decimal('vat_percentage', 5, 2)->default('0.00');
            $table->boolean('trial_all_countries')->default(false);
            $table->decimal('credits', 12, 2)->default('0.00');
            $table->json('features')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_free')->default(false);
            $table->integer('trial_days')->default(0);
            $table->integer('sort_order')->default(0);
            $table->string('stripe_price_monthly_id')->nullable();
            $table->string('stripe_price_yearly_id')->nullable();
            $table->string('paypal_plan_monthly_id')->nullable();
            $table->string('paypal_plan_yearly_id')->nullable();
            $table->timestamps();
        });

        Schema::create('plan_country_prices', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('plan_id')->unsigned();
            $table->char('country_code', 2);
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('original_price_monthly', 12, 2)->nullable();
            $table->decimal('original_price_yearly', 12, 2)->nullable();
            $table->decimal('original_price_lifetime', 12, 2)->nullable();
            $table->decimal('price_monthly', 12, 2)->nullable();
            $table->decimal('price_yearly', 12, 2)->nullable();
            $table->decimal('price_lifetime', 12, 2)->nullable();
            $table->decimal('vat_percentage', 5, 2)->nullable();
            $table->boolean('trial_monthly_enabled')->default(false);
            $table->boolean('trial_yearly_enabled')->default(false);
            $table->boolean('trial_lifetime_enabled')->default(false);
            $table->integer('trial_monthly_days')->unsigned()->nullable();
            $table->integer('trial_yearly_days')->unsigned()->nullable();
            $table->integer('trial_lifetime_days')->unsigned()->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['plan_id', 'country_code']);
            $table->index('country_code');
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
        });

        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_test_mode')->default(true);
            $table->enum('processing_fee_type', ['none', 'percentage', 'fixed'])->default('none');
            $table->decimal('processing_fee_value', 10, 2)->default('0.00');
            $table->json('credentials')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_enabled', 'sort_order']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->enum('type', ['percent', 'fixed'])->default('percent');
            $table->decimal('value', 10, 2);
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->integer('max_uses')->nullable();
            // NULL is meaningful here: it means unlimited redemptions per user (see
            // Coupon::hasReachedUserLimit()), so this must stay nullable.
            $table->integer('per_user_limit')->unsigned()->nullable()->default(1);
            $table->integer('used_count')->default(0);
            $table->bigInteger('plan_id')->unsigned()->nullable();
            $table->string('user_limit', 40)->default('all')->index();
            $table->boolean('show_in_header')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('set null');
        });

        Schema::create('billing_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->bigInteger('plan_id')->unsigned();
            $table->bigInteger('scheduled_plan_id')->unsigned()->nullable();
            $table->string('scheduled_billing_cycle')->nullable();
            $table->timestamp('scheduled_change_at')->nullable();
            $table->enum('billing_cycle', ['monthly', 'yearly', 'lifetime'])->default('monthly');
            $table->enum('status', ['active', 'cancelled', 'expired', 'past_due', 'trialing'])->default('active');
            $table->string('gateway', 50)->nullable();
            $table->string('gateway_subscription_id')->nullable();
            $table->decimal('amount', 10, 2)->default('0.00');
            $table->string('currency', 3)->default('USD');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('credits_refreshed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
            $table->index(['user_id', 'status']);
            $table->index('gateway_subscription_id');
            $table->index('current_period_end');
            $table->index(['status', 'cancelled_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->bigInteger('user_id')->unsigned();
            $table->bigInteger('plan_id')->unsigned()->nullable();
            $table->bigInteger('subscription_id')->unsigned()->nullable();
            $table->string('gateway', 50);
            $table->string('gateway_payment_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->enum('type', ['subscription', 'credit_topup', 'one_time'])->default('subscription');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('set null');
            $table->foreign('subscription_id')->references('id')->on('billing_subscriptions')->onDelete('set null');
            $table->index(['user_id', 'status']);
            $table->index('gateway_payment_id');
            $table->index(['status', 'created_at']);
            $table->index(['status', 'type', 'created_at']);
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('coupon_id')->unsigned();
            $table->bigInteger('user_id')->unsigned();
            $table->bigInteger('user_unique_guard')->unsigned()->nullable();
            $table->bigInteger('payment_id')->unsigned()->nullable();
            $table->timestamps();

            $table->unique('payment_id');
            $table->unique(['coupon_id', 'user_unique_guard']);
            $table->index(['coupon_id', 'user_id']);
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
        });

        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->decimal('amount', 12, 4);
            $table->decimal('balance_after', 12, 4);
            $table->enum('type', ['purchase', 'usage', 'refund', 'bonus', 'referral', 'admin_adjust', 'topup']);
            $table->string('description', 500);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'created_at']);
        });

        // Cashier standard tables
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->string('type');
            $table->string('stripe_id')->unique();
            $table->string('stripe_status');
            $table->string('stripe_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'stripe_status']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('subscription_id')->unsigned();
            $table->string('stripe_id')->unique();
            $table->string('stripe_product');
            $table->string('stripe_price');
            $table->string('meter_id')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('meter_event_name')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'stripe_price']);
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('billing_subscriptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('payment_gateways');
        Schema::dropIfExists('plan_country_prices');
        Schema::dropIfExists('plans');
    }
};
