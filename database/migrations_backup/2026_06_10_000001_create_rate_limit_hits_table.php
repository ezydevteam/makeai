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
            $table->string('key');
            $table->string('category', 100);
            $table->unsignedInteger('hits')->default(1);
            $table->unsignedBigInteger('window_start');
            $table->unsignedInteger('window_seconds');
            $table->timestamps();

            $table->unique(['key', 'window_start']);
            $table->index(['key', 'category']);
            $table->index('window_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_limit_hits');
    }
};
