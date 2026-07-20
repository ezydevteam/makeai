<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_limit_rules', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('tier');
            $table->integer('max_attempts')->unsigned();
            $table->integer('window_seconds')->unsigned();
            $table->timestamps();

            $table->unique(['category', 'tier']);
        });

        Schema::create('rate_limit_hits', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('category', 100);
            $table->integer('hits')->unsigned()->default(1);
            $table->bigInteger('window_start')->unsigned();
            $table->integer('window_seconds')->unsigned();
            $table->timestamps();

            $table->unique(['key', 'window_start']);
            $table->index(['key', 'category']);
            $table->index('window_start');
        });

        Schema::create('user_rate_limit_overrides', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->string('category');
            $table->integer('max_attempts');
            $table->integer('window_seconds');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'category']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_rate_limit_overrides');
        Schema::dropIfExists('rate_limit_hits');
        Schema::dropIfExists('rate_limit_rules');
    }
};
