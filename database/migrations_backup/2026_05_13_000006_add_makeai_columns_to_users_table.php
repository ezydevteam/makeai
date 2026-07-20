<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add MakeAI-specific columns to users table.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('ulid')->unique()->after('id');
            $table->string('avatar', 255)->nullable()->after('name');
            $table->decimal('credits', 12, 2)->default(0)->after('password');
            $table->unsignedBigInteger('plan_id')->nullable()->after('credits');
            $table->enum('theme_preference', ['light', 'dark', 'system'])->default('system')->after('plan_id');
            $table->string('locale', 10)->default('en')->after('theme_preference');
            $table->boolean('is_active')->default(true)->after('locale');

            // OTP verification
            $table->string('otp_code', 10)->nullable();
            $table->timestamp('otp_expires_at')->nullable();

            // OAuth
            $table->string('oauth_provider', 50)->nullable();
            $table->string('oauth_id', 255)->nullable();

            // Tracking
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'ulid', 'avatar', 'credits', 'plan_id', 'theme_preference',
                'locale', 'is_active', 'otp_code', 'otp_expires_at',
                'oauth_provider', 'oauth_id', 'last_login_at', 'last_login_ip',
            ]);
        });
    }
};
