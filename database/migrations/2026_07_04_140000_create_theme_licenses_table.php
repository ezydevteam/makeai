<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-theme license storage — mirrors addon_licenses. A premium theme (one with an
 * envato_item_id in its manifest) must be activated with its own purchase code,
 * verified against the License Server (product=theme).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('theme_slug', 100)->unique();
            $table->text('purchase_code')->comment('encrypted with APP_KEY');
            $table->tinyInteger('license_type')->comment('1=Regular, 2=Extended');
            $table->string('buyer', 100)->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('supported_until')->nullable();
            $table->string('domain', 255);
            $table->timestamp('verified_at');
            $table->enum('status', ['valid', 'grace', 'invalid'])->default('valid');
            $table->timestamp('grace_started_at')->nullable();
            $table->text('signed_payload')->nullable();
            $table->text('signature')->nullable();
            $table->timestamps();

            $table->index(['theme_slug', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_licenses');
    }
};
