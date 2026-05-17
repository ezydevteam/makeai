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
        // 1. AI Models Table — Per-model configuration and cost tracking
        Schema::create('ai_models', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('slug')->unique(); // e.g. gpt-4o, claude-3-5-sonnet
            $blueprint->string('name');
            $blueprint->string('provider'); // openai, anthropic, google, etc.
            $blueprint->boolean('is_active')->default(true);

            // Cost per 1K tokens in USD
            $blueprint->decimal('cost_input_1k', 12, 8)->default(0.00000000);
            $blueprint->decimal('cost_output_1k', 12, 8)->default(0.00000000);

            // Credits per 1K tokens (if using credit-based system instead of direct USD)
            $blueprint->integer('credits_per_1k')->default(1);

            $blueprint->integer('max_tokens')->default(4096);
            $blueprint->integer('rate_limit_per_min')->nullable();

            // Support for different types: chat, image, voice, etc.
            $blueprint->string('type')->default('chat');

            $blueprint->json('meta')->nullable(); // For additional provider-specific settings
            $blueprint->timestamps();
            $blueprint->softDeletes();
        });

        // 2. AI API Keys Table — Multiple keys per provider for load balancing
        Schema::create('ai_keys', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('provider'); // openai, anthropic, google, etc.
            $blueprint->string('api_key'); // Encrypted at model level if needed
            $blueprint->string('label')->nullable(); // "Backup Key", "Main Key"
            $blueprint->boolean('is_active')->default(true);

            // Tracking usage for load balancing (round-robin/least-used)
            $blueprint->timestamp('last_used_at')->nullable();
            $blueprint->unsignedBigInteger('usage_count')->default(0);
            $blueprint->unsignedBigInteger('error_count')->default(0);
            $blueprint->timestamp('disabled_until')->nullable(); // Auto-disable on repeated errors

            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_keys');
        Schema::dropIfExists('ai_models');
    }
};
