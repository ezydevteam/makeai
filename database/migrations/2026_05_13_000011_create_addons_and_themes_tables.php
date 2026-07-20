<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name');
            $table->string('version', 20)->default('0.0.0');
            $table->boolean('is_active')->default(false);
            $table->json('manifest')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('addon_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('addon_slug', 100)->unique();
            $table->text('purchase_code')->comment('encrypted with APP_KEY');
            $table->tinyInteger('license_type')->comment('1=Regular, 2=Extended');
            $table->string('buyer', 100)->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('supported_until')->nullable();
            $table->string('domain');
            $table->timestamp('verified_at');
            $table->enum('status', ['valid', 'grace', 'invalid'])->default('valid');
            $table->timestamp('grace_started_at')->nullable();
            $table->text('signed_payload')->nullable();
            $table->text('signature')->nullable();
            $table->timestamps();

            $table->index(['addon_slug', 'status']);
        });

        Schema::create('addon_settings', function (Blueprint $table) {
            $table->id();
            $table->string('addon_slug', 100);
            $table->string('key', 191);
            $table->longText('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->timestamps();

            $table->unique(['addon_slug', 'key']);
            $table->index('addon_slug');
        });

        Schema::create('theme_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('theme_slug', 100)->unique();
            $table->text('purchase_code')->comment('encrypted with APP_KEY');
            $table->tinyInteger('license_type')->comment('1=Regular, 2=Extended');
            $table->string('buyer', 100)->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('supported_until')->nullable();
            $table->string('domain');
            $table->timestamp('verified_at');
            $table->enum('status', ['valid', 'grace', 'invalid'])->default('valid');
            $table->timestamp('grace_started_at')->nullable();
            $table->text('signed_payload')->nullable();
            $table->text('signature')->nullable();
            $table->timestamps();

            $table->index(['theme_slug', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('theme_licenses');
        Schema::dropIfExists('addon_settings');
        Schema::dropIfExists('addon_licenses');
        Schema::dropIfExists('addons');
    }
};
