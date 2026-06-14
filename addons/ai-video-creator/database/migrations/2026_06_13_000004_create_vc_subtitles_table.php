<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vc_subtitles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vc_render_id')->constrained('vc_renders')->cascadeOnDelete();
            $table->string('provider', 30)->default('whisper');
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued');
            $table->string('language', 10)->default('en');
            $table->enum('format', ['srt', 'vtt', 'json'])->default('srt');
            $table->longText('content')->nullable();
            $table->json('segments')->nullable();
            $table->decimal('credits_deducted', 10, 4)->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['vc_render_id', 'format'], 'vc_subtitles_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vc_subtitles');
    }
};
