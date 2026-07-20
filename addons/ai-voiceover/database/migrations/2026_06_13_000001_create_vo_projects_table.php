<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vo_projects', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->enum('type', ['voiceover', 'podcast'])->default('voiceover');
            $table->text('description')->nullable();
            $table->string('cover_art_path', 500)->nullable();
            $table->string('cover_art_url', 500)->nullable();
            $table->string('podcast_author', 150)->nullable();
            $table->string('podcast_category', 100)->nullable();
            $table->string('podcast_language', 10)->default('en');
            $table->boolean('podcast_explicit')->default(false);
            $table->string('rss_token', 64)->nullable()->unique();
            $table->boolean('rss_enabled')->default(false);
            $table->unsignedInteger('total_duration')->default(0);
            $table->unsignedInteger('episode_count')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'type']);
            $table->index('rss_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vo_projects');
    }
};
