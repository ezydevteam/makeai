<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('ads');

        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['image', 'script'])->default('image');
            $table->enum('placement', ['sidebar', 'top', 'bottom', 'feed', 'blog_side'])->default('sidebar');
            $table->longText('content')->nullable(); // for script ads
            $table->string('image_path')->nullable();
            $table->string('link_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('views')->default(0);
            $table->integer('clicks')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
