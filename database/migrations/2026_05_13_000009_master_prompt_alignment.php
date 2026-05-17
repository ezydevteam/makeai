<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master Prompt alignment — adds all missing columns/tables.
     * Ref: AI_SaaS_Master_Prompt Part 4.1, Part 6
     */
    public function up(): void
    {
        // ─── Fix users table — add missing columns from Part 4.1 ──
        Schema::table('users', function (Blueprint $table) {
            // Credits tracking (Part 4.1)
            $table->decimal('credits_used_today', 12, 4)->default(0)->after('credits');
            $table->decimal('credits_used_month', 12, 4)->default(0)->after('credits_used_today');
            $table->decimal('daily_limit', 12, 4)->nullable()->after('credits_used_month');
            $table->decimal('monthly_limit', 12, 4)->nullable()->after('daily_limit');

            // Subscription status on user (Part 4.1)
            $table->enum('subscription_status', ['active', 'trialing', 'past_due', 'canceled', 'none'])
                ->default('none')->after('plan_id');
            $table->timestamp('subscription_ends_at')->nullable()->after('subscription_status');
            $table->timestamp('trial_ends_at')->nullable()->after('subscription_ends_at');

            // Referral system (Part 4.1)
            $table->string('referral_code', 20)->unique()->nullable()->after('trial_ends_at');
            $table->unsignedBigInteger('referred_by')->nullable()->after('referral_code');
            $table->decimal('referral_earnings', 12, 4)->default(0)->after('referred_by');
            $table->integer('referral_count')->default(0)->after('referral_earnings');

            // Security — 2FA (Part 4.1)
            $table->string('two_factor_secret')->nullable()->after('otp_expires_at');
            $table->boolean('two_factor_enabled')->default(false)->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_enabled');
            $table->integer('login_attempts')->default(0)->after('two_factor_confirmed_at');
            $table->timestamp('locked_until')->nullable()->after('login_attempts');

            // Preferences (Part 4.1)
            $table->string('timezone', 50)->default('UTC')->after('locale');
            $table->json('personal_api_keys')->nullable()->after('theme_preference');
            $table->text('brand_voice')->nullable()->after('personal_api_keys');

            // Status (Part 4.1)
            $table->boolean('is_banned')->default(false)->after('is_active');
            $table->text('ban_reason')->nullable()->after('is_banned');

            // Meta (Part 4.1)
            $table->boolean('email_marketing')->default(true)->after('last_login_ip');
            $table->softDeletes();

            // Index
            $table->index('referral_code');
        });

        // ─── Credit Transactions (Part 4.1) ──────────────────────
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 4);                     // positive = add, negative = deduct
            $table->decimal('balance_after', 12, 4);
            $table->enum('type', ['purchase', 'usage', 'refund', 'bonus', 'referral', 'admin_adjust']);
            $table->string('description', 500);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // ─── Login History (Part 4.1) ────────────────────────────
        Schema::create('login_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip', 45);
            $table->text('user_agent')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->boolean('success')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // ─── Fix plans table — add missing columns (Part 6) ──────
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('price_lifetime', 10, 2)->nullable()->after('price_yearly');
            $table->integer('trial_days')->default(0)->after('is_free');
        });

        // ─── Coupons (Part 6) ────────────────────────────────────
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->enum('type', ['percent', 'fixed'])->default('percent');
            $table->decimal('value', 10, 2);                      // % or fixed USD
            $table->decimal('max_discount', 10, 2)->nullable();   // cap for percent
            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);
            $table->boolean('is_recurring')->default(false);       // applies on renewal too
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete(); // limit to plan
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('login_history');
        Schema::dropIfExists('credit_transactions');

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['price_lifetime', 'trial_days']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex(['referral_code']);
            $table->dropColumn([
                'credits_used_today', 'credits_used_month', 'daily_limit', 'monthly_limit',
                'subscription_status', 'subscription_ends_at', 'trial_ends_at',
                'referral_code', 'referred_by', 'referral_earnings', 'referral_count',
                'two_factor_secret', 'two_factor_enabled', 'two_factor_confirmed_at',
                'login_attempts', 'locked_until',
                'timezone', 'personal_api_keys', 'brand_voice',
                'is_banned', 'ban_reason', 'email_marketing',
            ]);
        });
    }
};
