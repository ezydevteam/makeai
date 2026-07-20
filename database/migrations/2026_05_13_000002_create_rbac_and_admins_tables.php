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
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('admin_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 150)->unique();
            $table->string('group', 50);
            $table->timestamps();
        });

        Schema::create('admin_role_permissions', function (Blueprint $table) {
            $table->bigInteger('role_id')->unsigned();
            $table->bigInteger('permission_id')->unsigned();

            $table->primary(['role_id', 'permission_id']);
            $table->foreign('role_id')->references('id')->on('admin_roles')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('admin_permissions')->onDelete('cascade');
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('password_changed_at')->nullable();
            $table->string('avatar')->nullable();
            $table->bigInteger('role_id')->unsigned();
            $table->boolean('is_active')->default(true);
            $table->text('two_factor_secret')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->string('otp_secret')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('role_id')->references('id')->on('admin_roles')->onDelete('restrict');
        });

        Schema::create('banned_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->string('reason', 255)->nullable();
            $table->string('category', 255)->default('all');
            $table->timestamp('banned_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->bigInteger('banned_by')->unsigned()->nullable();

            $table->unique(['ip_address', 'category'], 'banned_ips_ip_address_category_unique');
            $table->foreign('banned_by')->references('id')->on('admins')->onDelete('set null');
        });

        Schema::create('admin_notes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('admin_id')->unsigned();
            $table->string('subject', 255);
            $table->text('description')->nullable();
            $table->dateTime('reminder_date')->nullable();
            $table->dateTime('auto_delete_date')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('cascade');
        });

        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('admin_id')->unsigned();
            $table->string('action');
            $table->string('route_name')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at');

            $table->index(['admin_id', 'created_at']);
            $table->index('action');
            $table->index('route_name');
            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('cascade');
        });

        Schema::create('admin_login_history', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('admin_id')->unsigned();
            $table->string('ip', 45);
            $table->text('user_agent')->nullable();
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->boolean('success')->default(true);
            $table->timestamps();

            $table->index(['admin_id', 'created_at']);
            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_login_history');
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('admin_notes');
        Schema::dropIfExists('banned_ips');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('admin_role_permissions');
        Schema::dropIfExists('admin_permissions');
        Schema::dropIfExists('admin_roles');
    }
};
