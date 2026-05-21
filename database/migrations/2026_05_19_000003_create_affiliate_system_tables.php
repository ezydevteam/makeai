<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'affiliate_custom_slug')) {
                $table->string('affiliate_custom_slug', 60)->nullable()->unique()->after('referral_code');
            }
        });

        Schema::create('affiliate_programs', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(false);
            $table->enum('commission_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('commission_value', 8, 2)->default(20);
            $table->enum('commission_on', ['first_purchase', 'all_purchases', 'subscription'])->default('first_purchase');
            $table->unsignedInteger('cookie_days')->default(30);
            $table->decimal('min_payout', 10, 2)->default(20);
            $table->json('payout_methods')->nullable();
            $table->boolean('auto_approve_commissions')->default(false);
            $table->unsignedInteger('commission_hold_days')->default(14);
            $table->boolean('allow_custom_alias')->default(false);
            $table->json('marketing_banners')->nullable();
            $table->json('promotional_emails')->nullable();
            $table->json('social_posts')->nullable();
            $table->longText('terms')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('referral_code', 20);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('landed_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'created_at']);
            $table->index(['referred_id', 'converted_at']);
            $table->index('referral_code');
        });

        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->decimal('amount', 10, 4);
            $table->enum('status', ['pending', 'approved', 'paid', 'rejected', 'cancelled'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'status']);
            $table->index(['referred_id', 'order_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('affiliate_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['paypal', 'bank_transfer', 'credits']);
            $table->enum('status', ['pending', 'processing', 'paid', 'rejected'])->default('pending');
            $table->json('payout_details')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_payouts');
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliate_referrals');
        Schema::dropIfExists('affiliate_programs');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'affiliate_custom_slug')) {
                $table->dropUnique(['affiliate_custom_slug']);
                $table->dropColumn('affiliate_custom_slug');
            }
        });
    }
};
