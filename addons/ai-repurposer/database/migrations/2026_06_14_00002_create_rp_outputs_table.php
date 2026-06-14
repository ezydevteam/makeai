<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rp_outputs', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('rp_job_id')->constrained('rp_jobs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('format', 50);
            $table->longText('content');
            $table->unsignedInteger('word_count')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_saved')->default(false);
            $table->unsignedBigInteger('saved_post_id')->nullable();
            $table->timestamps();

            $table->unique(['rp_job_id', 'format']);
            $table->index(['user_id', 'format']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rp_outputs');
    }
};
