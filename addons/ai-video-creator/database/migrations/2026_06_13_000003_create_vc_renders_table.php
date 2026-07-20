<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vc_renders', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vc_project_id')->nullable()->constrained('vc_projects')->nullOnDelete();
            $table->enum('type', ['text_to_video', 'image_to_video', 'avatar_video', 'slideshow']);
            $table->enum('status', ['queued', 'processing', 'completed', 'failed', 'cancelled'])->default('queued');
            $table->string('provider', 30);
            $table->string('provider_job_id', 255)->nullable();
            $table->unsignedTinyInteger('poll_attempts')->default(0);
            $table->string('title', 255)->nullable();
            $table->text('prompt')->nullable();
            $table->text('script')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->string('aspect_ratio', 10)->default('16:9');
            $table->string('resolution', 20)->default('1280x720');
            $table->json('provider_settings')->nullable();
            $table->string('input_media_path', 500)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('file_url', 500)->nullable();
            $table->unsignedInteger('file_size_bytes')->default(0);
            $table->string('thumbnail_path', 500)->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->unsignedSmallInteger('duration_actual')->nullable();
            $table->boolean('share_enabled')->default(false);
            $table->string('share_token', 64)->nullable()->unique();
            $table->decimal('credits_deducted', 10, 4)->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'provider_job_id']);
            $table->index(['expires_at']);
            $table->index(['share_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vc_renders');
    }
};
