<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ss_scheduled_posts', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ss_campaign_id')->nullable()->constrained('ss_campaigns')->nullOnDelete();
            $table->string('title', 255)->nullable();
            $table->text('caption');
            $table->text('hashtags')->nullable();
            $table->json('platforms');
            $table->enum('status', ['draft', 'pending_approval', 'scheduled', 'publishing', 'published', 'partial', 'failed', 'cancelled'])->default('draft');
            $table->enum('post_type', ['single', 'carousel', 'thread', 'story', 'reel'])->default('single');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_rss_auto')->default(false);
            $table->foreignId('rss_feed_id')->nullable()->constrained('ss_rss_feeds')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('first_comment')->nullable();
            $table->json('platform_overrides')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'scheduled_at']);
            $table->index(['scheduled_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ss_scheduled_posts');
    }
};
