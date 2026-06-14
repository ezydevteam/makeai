<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rp_jobs', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('source_type', ['youtube_url', 'file_upload', 'text_paste'])->default('youtube_url');
            $table->string('source_url', 500)->nullable();
            $table->string('source_path', 500)->nullable();
            $table->string('source_title', 500)->nullable();
            $table->longText('transcript')->nullable();
            $table->string('transcript_source', 50)->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('chapters')->nullable();
            $table->enum('status', ['queued', 'transcribing', 'generating', 'completed', 'failed', 'partial'])->default('queued');
            $table->json('formats_requested')->nullable();
            $table->json('formats_completed')->nullable();
            $table->decimal('credits_deducted', 10, 4)->default(0);
            $table->text('error_message')->nullable();
            $table->boolean('is_bulk')->default(false);
            $table->string('bulk_batch_id', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('bulk_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rp_jobs');
    }
};
