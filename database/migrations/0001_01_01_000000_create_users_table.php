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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('name');
            $table->string('country', 2)->nullable();
            $table->string('profession', 150)->nullable();
            // National phone number (digits/formatting only, no dial code) paired with
            // phone_country — the ISO alpha-2 whose dial code the PhoneInput prefixes.
            $table->string('phone', 32)->nullable();
            $table->string('phone_country', 2)->nullable();
            // Set once the user confirms an SMS OTP sent to `phone`; reset to null
            // whenever phone/phone_country changes so re-verification is required.
            $table->timestamp('phone_verified_at')->nullable();
            // Consent to receive non-essential/bulk SMS. Defaults on (opt-out model,
            // matching email_marketing); users can turn it off in Privacy settings.
            // Bulk campaigns still additionally require a verified phone.
            $table->boolean('sms_marketing_opt_in')->default(true);
            $table->string('avatar')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->timestamp('password_changed_at')->nullable();
            $table->decimal('credits', 12, 4)->default('0.0000');
            $table->decimal('credits_used_today', 12, 4)->default('0.0000');
            $table->decimal('credits_used_month', 12, 4)->default('0.0000');
            $table->decimal('daily_limit', 12, 4)->nullable();
            $table->decimal('monthly_limit', 12, 4)->nullable();
            $table->bigInteger('plan_id')->unsigned()->nullable();
            $table->enum('subscription_status', ['active', 'trialing', 'past_due', 'canceled', 'cancelled', 'none'])->default('none');
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('referral_code', 20)->nullable()->unique();
            $table->string('affiliate_custom_slug', 60)->nullable()->unique();
            $table->bigInteger('referred_by')->unsigned()->nullable();
            $table->decimal('referral_earnings', 12, 4)->default('0.0000');
            $table->integer('referral_count')->default(0);
            $table->boolean('affiliate_banned')->default(false);
            $table->enum('theme_preference', ['light', 'dark', 'system'])->default('system');
            $table->text('brand_voice')->nullable();
            $table->json('preferences')->nullable();
            $table->json('cookie_consent')->nullable();
            $table->boolean('allow_data_improve')->default(true);
            $table->timestamp('scheduled_deletion_at')->nullable();
            $table->string('locale', 10)->default('en');
            $table->string('timezone', 50)->default('UTC');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_internal')->default(false);
            $table->boolean('has_trialed')->default(false);
            $table->boolean('is_banned')->default(false);
            $table->text('ban_reason')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->string('otp_code')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->tinyInteger('otp_attempts')->unsigned()->default(0);
            $table->timestamp('otp_locked_until')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            // The second-factor channel the user chose: 'totp' (authenticator app) or
            // 'sms' (one-time code texted to their verified phone).
            $table->string('two_factor_channel', 16)->default('totp');
            $table->integer('login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->string('oauth_provider', 50)->nullable();
            $table->string('oauth_id')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->string('use_case', 50)->nullable();
            $table->json('dismissed_tooltips')->nullable();
            $table->boolean('email_marketing')->default(true);
            $table->json('notification_preferences')->nullable();
            $table->softDeletes();
            $table->string('stripe_id')->nullable();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->text('chat_custom_instructions')->nullable();

            $table->unique(['oauth_provider', 'oauth_id']);
            // Composite so the same national number can recur across countries; the pair
            // (national number + phone_country dial code) identifies one real subscriber.
            // NULL is treated as distinct, so phone stays optional at the DB layer — the
            // "Require Phone Number" rule is enforced in the app (phone_requirement_met()).
            $table->unique(['phone', 'phone_country'], 'users_phone_phone_country_unique');
            $table->index('is_active');
            $table->index('referral_code');
            $table->index('stripe_id');
            $table->index(['email_marketing', 'last_login_at']);
            $table->index(['subscription_status', 'is_active']);
            $table->index('created_at');
            $table->index(['subscription_status', 'subscription_ends_at']);
            $table->index(['is_active', 'created_at']);
            $table->index('is_internal');
            // Fulltext indexes are MySQL-only; sqlite (used in tests) can't build them and the
            // app falls back to LIKE search there. Guard so migrations stay sqlite-safe.
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText(['name', 'email'], 'live_search_users_fulltext');
            }
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
