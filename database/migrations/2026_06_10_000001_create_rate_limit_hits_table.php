<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_limit_hits', function (Blueprint $table) {
            $table->id();
            $table->string('key', 255)->index();
            $table->string('category', 100);
            $table->unsignedInteger('hits')->default(1);
            $table->unsignedInteger('window_start');
            $table->unsignedInteger('window_seconds');
            $table->timestamps();

            $table->unique(['key', 'window_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_limit_hits');
    }
};
