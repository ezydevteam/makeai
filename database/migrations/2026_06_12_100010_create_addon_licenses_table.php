<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('addon_slug', 100)->unique();
            $table->text('purchase_code')->comment('encrypted with APP_KEY'); // encrypted
            $table->bigInteger('envato_item_id');
            $table->tinyInteger('license_type')->comment('1=Regular, 2=Extended');
            $table->string('buyer', 100)->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('supported_until')->nullable();
            $table->string('domain', 255);
            $table->timestamp('verified_at');
            $table->enum('status', ['valid', 'grace', 'invalid'])->default('valid');
            $table->timestamp('grace_started_at')->nullable();
            $table->timestamps();

            $table->index(['addon_slug', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_licenses');
    }
};
