<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ss_post_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ss_post_platform_id')->constrained('ss_post_platforms')->cascadeOnDelete();
            $table->string('platform', 30);
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('reach')->default(0);
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('shares')->default(0);
            $table->unsignedInteger('saves')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('video_views')->default(0);
            $table->decimal('engagement_rate', 5, 2)->default(0.00);
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['ss_post_platform_id'], 'ss_post_analytics_unique');
            $table->index(['platform', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ss_post_analytics');
    }
};
