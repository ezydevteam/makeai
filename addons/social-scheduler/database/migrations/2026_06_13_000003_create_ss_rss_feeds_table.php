<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ss_rss_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('title', 255)->nullable();
            $table->json('platforms');
            $table->text('caption_prompt')->nullable();
            $table->enum('status', ['active', 'paused', 'error'])->default('active');
            $table->timestamp('last_polled_at')->nullable();
            $table->text('last_error')->nullable();
            $table->string('last_item_guid', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ss_rss_feeds');
    }
};
