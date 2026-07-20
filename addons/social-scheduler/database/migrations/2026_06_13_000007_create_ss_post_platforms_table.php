<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ss_post_platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ss_scheduled_post_id')->constrained('ss_scheduled_posts')->cascadeOnDelete();
            $table->foreignId('ss_social_account_id')->constrained('ss_social_accounts')->cascadeOnDelete();
            $table->string('platform', 30);
            $table->enum('status', ['pending', 'publishing', 'published', 'failed', 'skipped'])->default('pending');
            $table->string('external_post_id', 255)->nullable();
            $table->string('external_post_url', 500)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamps();

            $table->unique(['ss_scheduled_post_id', 'ss_social_account_id'], 'ss_post_platforms_unique');
            $table->index(['status', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ss_post_platforms');
    }
};
