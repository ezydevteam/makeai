<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vo_episodes', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->unsignedBigInteger('vo_project_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('music_track_id')->nullable();
            $table->string('title');
            $table->unsignedSmallInteger('episode_number')->nullable();
            $table->unsignedSmallInteger('season_number')->nullable();
            $table->longText('script')->nullable();
            $table->json('segments')->nullable();
            $table->enum('status', ['draft', 'queued', 'processing', 'completed', 'failed'])->default('draft');
            $table->string('provider', 30)->nullable();
            $table->string('voice_id', 100)->nullable();
            $table->decimal('music_volume', 3, 2)->nullable()->default(0.30);
            $table->string('file_path', 500)->nullable();
            $table->string('file_url', 500)->nullable();
            $table->unsignedInteger('file_size_bytes')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('waveform_path', 500)->nullable();
            $table->string('waveform_url', 500)->nullable();
            $table->string('format', 10)->default('mp3');
            $table->text('transcript_srt')->nullable();
            $table->text('transcript_vtt')->nullable();
            $table->decimal('credits_deducted', 10, 4)->default(0);
            $table->text('error_message')->nullable();
            $table->string('share_token', 64)->nullable()->unique();
            $table->boolean('share_enabled')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('vo_project_id')->references('id')->on('vo_projects')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['vo_project_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('share_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vo_episodes');
    }
};
