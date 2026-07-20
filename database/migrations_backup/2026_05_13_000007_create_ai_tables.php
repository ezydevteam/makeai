<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI infrastructure — tools, reviews, reviews votes, usage logs, chat.
     */
    public function up(): void
    {
        // AI Tools — each row is one AI task (blog article generator, etc.)
        Schema::create('ai_tools', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->nullable()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('prompt_system')->nullable();
            $table->text('prompt_user')->nullable();
            $table->foreignId('category_id')->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('color', 20)->nullable();
            $table->json('fields')->nullable();
            $table->string('output_type', 20)->default('markdown');
            $table->string('model_override', 100)->nullable();
            $table->unsignedInteger('max_tokens_override')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->string('access_level', 30)->default('inherit');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_system')->default(true);
            $table->boolean('requires_pro')->default(false);
            $table->boolean('supports_brand_voice')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('usage_count')->default(0);
            $table->unsignedInteger('avg_output_tokens')->default(400);
            $table->unsignedInteger('avg_latency_ms')->nullable();
            $table->decimal('avg_rating', 3, 2)->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_image')->nullable();
            $table->longText('about_content')->nullable();
            $table->json('how_it_works')->nullable();
            $table->json('usage_examples')->nullable();
            $table->json('faq_items')->nullable();
            $table->boolean('show_about')->default(true);
            $table->boolean('show_how_it_works')->default(true);
            $table->boolean('show_usage_examples')->default(true);
            $table->boolean('show_faqs')->default(true);
            $table->boolean('show_reviews')->default(true);
            $table->boolean('show_related_tools')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
        });

        // AI Usage Logs — tracks every AI request for billing + analytics
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('model', 100);
            $table->string('type', 30);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->decimal('credits_used', 10, 2)->default(0);
            $table->string('status', 20)->default('completed');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['provider', 'model']);
            $table->index('type');
        });

        // Chat Conversations
        Schema::create('ai_chats', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255)->default('New Chat');
            $table->string('model', 100)->nullable();
            $table->string('category', 50)->default('general');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        // Chat Messages
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('ai_chats')->cascadeOnDelete();
            $table->enum('role', ['system', 'user', 'assistant']);
            $table->text('content');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('chat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chats');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_tools');
    }
};
