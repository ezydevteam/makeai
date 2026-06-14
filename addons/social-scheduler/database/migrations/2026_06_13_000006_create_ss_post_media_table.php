<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ss_post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ss_scheduled_post_id')->constrained('ss_scheduled_posts')->cascadeOnDelete();
            $table->foreignId('carousel_slide_id')->nullable()->constrained('ss_carousel_slides')->nullOnDelete();
            $table->enum('type', ['image', 'video', 'gif'])->default('image');
            $table->string('path', 500);
            $table->string('url', 500);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size_bytes')->default(0);
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->string('alt_text', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['ss_scheduled_post_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ss_post_media');
    }
};
