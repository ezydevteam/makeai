<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI infrastructure tables — templates, usage logs, chat history.
     */
    public function up(): void
    {
        // AI Templates — predefined prompts for different use cases
        Schema::create('ai_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('prompt');                       // system prompt with placeholders
            $table->string('category', 50);              // writer, code, image, chat, etc.
            $table->string('icon', 50)->nullable();
            $table->string('color', 20)->nullable();
            $table->json('fields')->nullable();           // dynamic input fields definition
            $table->string('default_model', 100)->nullable();
            $table->integer('max_tokens')->default(2048);
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('usage_count')->default(0);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        // AI Usage Logs — tracks every AI request for billing + analytics
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);              // openai, anthropic, google, etc.
            $table->string('model', 100);                // gpt-4o-mini, claude-sonnet, etc.
            $table->string('type', 30);                  // chat, image, code, tts, stt
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->decimal('credits_used', 10, 2)->default(0);
            $table->string('status', 20)->default('completed'); // completed, failed, cancelled
            $table->json('metadata')->nullable();         // template_id, chat_id, etc.
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
            $table->json('metadata')->nullable();         // attachments, model used, etc.
            $table->timestamps();

            $table->index('chat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chats');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_templates');
    }
};
