<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admins table — completely separate from users.
     */
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('avatar', 255)->nullable();
            $table->foreignId('role_id')->constrained('admin_roles')->restrictOnDelete();
            $table->boolean('is_active')->default(true);

            // 2FA — TOTP (Google Authenticator)
            $table->string('two_factor_secret', 255)->nullable();
            $table->boolean('two_factor_enabled')->default(false);

            // 2FA — Email OTP
            $table->string('otp_secret', 10)->nullable();
            $table->timestamp('otp_expires_at')->nullable();

            // Tracking
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
