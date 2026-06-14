<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ss_carousel_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ss_scheduled_post_id')->constrained('ss_scheduled_posts')->cascadeOnDelete();
            $table->unsignedSmallInteger('slide_index');
            $table->text('caption')->nullable();
            $table->timestamps();

            $table->unique(['ss_scheduled_post_id', 'slide_index'], 'ss_carousel_slides_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ss_carousel_slides');
    }
};
