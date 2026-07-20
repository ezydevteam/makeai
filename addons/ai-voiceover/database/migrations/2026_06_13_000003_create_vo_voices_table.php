<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vo_voices', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('provider_voice_id', 100);
            $table->string('name', 150);
            $table->string('gender', 20)->nullable();
            $table->string('language', 10)->default('en');
            $table->string('accent', 50)->nullable();
            $table->string('preview_url', 500)->nullable();
            $table->json('labels')->nullable();
            $table->boolean('is_cloned')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_voice_id']);
            $table->index(['provider', 'language', 'gender']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vo_voices');
    }
};
