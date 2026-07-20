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
        Schema::create('social_follow_counts', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->unique(); // facebook, twitter, youtube, etc.
            $table->unsignedBigInteger('count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_follow_counts');
    }
};
