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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('color', 20)->nullable();
            $table->string('type')->default('ai_tool');
            $table->bigInteger('parent_id')->unsigned()->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->boolean('requires_pro')->default(false);
            $table->boolean('requires_login')->default(false);
            $table->string('access_level', 30)->default('inherit');
            $table->integer('sort_order')->unsigned()->default(0);
            $table->integer('tools_count')->unsigned()->default(0);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('categories')->onDelete('set null');
            $table->index(['type', 'is_active']);
        });

        Schema::create('ai_tools', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->nullable()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 50)->default('text');
            $table->text('description')->nullable();
            $table->text('prompt_system')->nullable();
            $table->text('prompt_user')->nullable();
            $table->bigInteger('category_id')->unsigned()->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('color', 20)->nullable();
            $table->json('fields')->nullable();
            $table->json('tags')->nullable();
            $table->string('output_type', 20)->default('markdown');
            $table->string('model_override', 100)->nullable();
            $table->string('generation_mode', 32)->default('llm');
            $table->string('integration_slug', 60)->nullable();
            $table->integer('max_tokens_override')->unsigned()->nullable();
            $table->decimal('temperature', 3, 2)->default('0.70');
            $table->tinyInteger('max_variants')->default(3);
            $table->boolean('show_improve')->default(true);
            $table->string('access_level', 30)->default('inherit');
            $table->boolean('is_active')->default(true);
            $table->boolean('show_header')->default(true);
            $table->boolean('show_footer')->default(true);
            $table->boolean('is_embeddable')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_system')->default(true);
            $table->boolean('supports_brand_voice')->default(true);
            $table->integer('sort_order')->unsigned()->default(0);
            $table->bigInteger('usage_count')->unsigned()->default(0);
            $table->bigInteger('views_count')->unsigned()->default(0);
            $table->integer('avg_output_tokens')->unsigned()->default(400);
            $table->decimal('avg_rating', 3, 2)->default(0.00);
            $table->integer('review_count')->unsigned()->default(0);
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
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->index(['category_id', 'is_active']);
            $table->index('type');
            // Fulltext indexes are MySQL-only; sqlite (used in tests) can't build them and the
            // app falls back to LIKE search there. Guard so migrations stay sqlite-safe.
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText(['name', 'description'], 'live_search_ai_tools_fulltext');
            }
        });

        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('provider');
            $table->boolean('is_active')->default(true);
            $table->decimal('cost_input_1k', 12, 8)->default('0.00000000');
            $table->decimal('cost_output_1k', 12, 8)->default('0.00000000');
            $table->integer('credits_per_1k')->default(1);
            $table->boolean('credits_auto')->default(true);
            $table->integer('max_tokens')->default(4096);
            $table->integer('rate_limit_per_min')->nullable();
            $table->string('type')->default('chat');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->string('provider', 50);
            $table->string('model', 100);
            $table->string('type', 30);
            $table->string('tool_slug', 100)->nullable();
            $table->integer('input_tokens')->unsigned()->default(0);
            $table->integer('output_tokens')->unsigned()->default(0);
            $table->decimal('cost_usd', 10, 6)->default('0.000000');
            $table->decimal('credits_used', 10, 2)->default('0.00');
            $table->integer('response_time_ms')->unsigned()->nullable();
            $table->timestamp('aggregated_at')->nullable();
            $table->string('status', 20)->default('completed');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'created_at']);
            $table->index(['provider', 'model']);
            $table->index('type');
            $table->index('tool_slug');
            $table->index(['status', 'aggregated_at', 'created_at']);
            $table->index('created_at');
            $table->index(['tool_slug', 'created_at']);
            $table->index(['provider', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('ai_keys', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->text('api_key');
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->bigInteger('usage_count')->unsigned()->default(0);
            $table->bigInteger('error_count')->unsigned()->default(0);
            $table->timestamp('disabled_until')->nullable();
            $table->timestamps();
        });

        // Bring-your-own-key: per-user provider API keys (encrypted at the model layer).
        // Index/FK names keep the user_api_keys_* prefix from before the table was renamed.
        Schema::create('user_byok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->text('api_key');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'provider'], 'user_api_keys_user_id_provider_index');
        });

        Schema::create('ai_chats', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('New Chat');
            $table->string('model', 100)->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained('ai_chats')->cascadeOnDelete();
            $table->enum('role', ['system', 'user', 'assistant']);
            $table->text('content');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->string('title');
            $table->longText('content');
            $table->string('tool_slug', 100)->nullable();
            $table->integer('word_count')->unsigned()->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'created_at']);
            $table->index('tool_slug');
        });

        Schema::create('generation_history', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->bigInteger('user_id')->unsigned();
            $table->string('tool_slug', 100);
            $table->bigInteger('document_id')->unsigned()->nullable();
            $table->text('prompt_system');
            $table->text('prompt_user');
            $table->json('field_values');
            $table->string('model', 100);
            $table->string('provider', 50);
            $table->decimal('temperature', 3, 2)->default('0.70');
            $table->integer('max_tokens')->default(0);
            $table->text('output_preview');
            $table->integer('tokens_input')->default(0);
            $table->integer('tokens_output')->default(0);
            $table->boolean('is_favorited')->default(false);
            $table->string('label', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('document_id')->references('id')->on('documents')->onDelete('set null');
            $table->index(['user_id', 'tool_slug', 'created_at']);
            $table->index(['user_id', 'is_favorited']);
        });

        Schema::create('ai_output_ratings', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->bigInteger('user_id')->unsigned();
            $table->string('tool_slug', 100);
            $table->bigInteger('generation_history_id')->unsigned()->nullable();
            $table->tinyInteger('rating');
            $table->string('feedback_text', 500)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('provider', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'generation_history_id'], 'uq_user_gen_rating');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('generation_history_id')->references('id')->on('generation_history')->onDelete('set null');
            $table->index(['tool_slug', 'rating', 'created_at']);
            $table->index('user_id');
        });

        Schema::create('user_collections', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->tinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('user_collection_tools', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('collection_id')->unsigned();
            $table->string('tool_slug', 100);
            $table->tinyInteger('sort_order')->default(0);

            $table->unique(['collection_id', 'tool_slug']);
            $table->foreign('collection_id')->references('id')->on('user_collections')->onDelete('cascade');
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->string('favoriteable_type');
            $table->bigInteger('favoriteable_id')->unsigned();
            $table->timestamps();

            $table->unique(['user_id', 'favoriteable_type', 'favoriteable_id']);
            $table->index(['favoriteable_type', 'favoriteable_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('tool_chains', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->bigInteger('user_id')->unsigned();
            $table->string('name', 100);
            $table->json('steps');
            $table->timestamp('last_run_at')->nullable();
            $table->integer('run_count')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('tool_chain_runs', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->bigInteger('chain_id')->unsigned();
            $table->bigInteger('user_id')->unsigned();
            $table->string('status', 20)->default('running');
            $table->text('input')->nullable();
            $table->json('step_outputs')->nullable();
            $table->integer('total_tokens')->default(0);
            $table->text('error')->nullable();
            $table->decimal('total_credits', 12, 4)->default('0.0000');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('chain_id')->references('id')->on('tool_chains')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('tool_embeds', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->bigInteger('user_id')->unsigned();
            $table->string('tool_slug', 100);
            $table->string('token', 64)->unique();
            $table->string('label', 100)->nullable();
            $table->json('allowed_origins')->nullable();
            $table->string('password_hash')->nullable();
            $table->string('theme', 10)->default('auto');
            $table->string('primary_color', 7)->nullable();
            $table->boolean('show_branding')->default(true);
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'tool_slug']);
        });

        Schema::create('tool_page_views', function (Blueprint $table) {
            $table->id();
            $table->string('tool_slug', 200);
            $table->date('viewed_date');
            $table->bigInteger('views_count')->unsigned()->default(0);

            $table->unique(['tool_slug', 'viewed_date']);
            $table->index(['viewed_date', 'views_count']);
        });

        Schema::create('tool_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('tool_slug', 100);
            $table->bigInteger('user_id')->unsigned();
            $table->tinyInteger('rating');
            $table->text('comment')->nullable();
            $table->text('admin_reply')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->integer('helpful_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'tool_slug']);
            $table->foreign('tool_slug')->references('slug')->on('ai_tools')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['tool_slug', 'is_approved']);
            $table->index(['tool_slug', 'rating']);
        });

        Schema::create('tool_review_votes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('review_id')->unsigned();
            $table->bigInteger('user_id')->unsigned();
            $table->boolean('is_helpful');
            $table->timestamps();

            $table->unique(['review_id', 'user_id']);
            $table->foreign('review_id')->references('id')->on('tool_reviews')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('tool_slug_history', function (Blueprint $table) {
            $table->id();
            $table->string('old_slug', 200);
            $table->string('new_slug', 200);
            $table->string('model_type', 50);
            $table->timestamp('changed_at')->useCurrent();
        });

        Schema::create('analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('type')->nullable();
            $table->integer('total_requests')->unsigned()->default(0);
            $table->bigInteger('total_input_tokens')->unsigned()->default(0);
            $table->bigInteger('total_output_tokens')->unsigned()->default(0);
            $table->decimal('total_cost_usd', 12, 6)->default('0.000000');
            $table->decimal('total_credits', 12, 2)->default('0.00');
            $table->timestamps();

            $table->unique(['date', 'provider', 'model', 'type'], 'analytics_daily_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chats');
        Schema::dropIfExists('user_byok');
        Schema::dropIfExists('analytics_daily');
        Schema::dropIfExists('tool_slug_history');
        Schema::dropIfExists('tool_review_votes');
        Schema::dropIfExists('tool_reviews');
        Schema::dropIfExists('tool_page_views');
        Schema::dropIfExists('tool_embeds');
        Schema::dropIfExists('tool_chain_runs');
        Schema::dropIfExists('tool_chains');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('user_collection_tools');
        Schema::dropIfExists('user_collections');
        Schema::dropIfExists('ai_output_ratings');
        Schema::dropIfExists('generation_history');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('ai_keys');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('ai_tools');
        Schema::dropIfExists('categories');
    }
};
