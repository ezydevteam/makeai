<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settings table — key-value store for all app configuration.
     * Cached via Redis, auto-invalidated on write.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 255)->unique();
            $table->longText('value')->nullable();
            $table->enum('type', ['string', 'boolean', 'integer', 'json', 'encrypted'])->default('string');
            $table->string('group', 100)->nullable()->index(); // general, ai, mail, payment, license
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
