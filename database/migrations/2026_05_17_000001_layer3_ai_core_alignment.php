<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * LAYER 3 — AI CORE alignment.
     * Ref: AI_SaaS_Master_Prompt Parts P11–P15
     *
     * 1. Aligns ai_templates to master prompt schema
     * 2. Creates tool_reviews + tool_review_votes tables
     * 3. Creates ai_tool_categories table
     */
    public function up(): void
    {
        // ─── 1. AI Tool Categories ────────────────────────────────
        Schema::create('ai_tool_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('icon', 100)->nullable();       // Tabler icon class
            $table->string('color', 20)->nullable();        // hex for card accent
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_pro')->default(false); // all tools in this category become pro-only
            $table->integer('sort_order')->default(0);
            $table->integer('tools_count')->default(0);     // cached counter
            $table->timestamps();
        });

        // ─── 2. Align ai_templates to master prompt ──────────────
        Schema::table('ai_templates', function (Blueprint $table) {
            // Rename prompt → prompt_system + add prompt_user
            $table->text('prompt_system')->nullable()->after('description');
            $table->text('prompt_user')->nullable()->after('prompt_system');

            // Replace category string → category_id FK
            $table->unsignedBigInteger('category_id')->nullable()->after('prompt_user');
            $table->foreign('category_id')->references('id')->on('ai_tool_categories')->nullOnDelete();

            // Output type
            $table->enum('output_type', [
                'text', 'markdown', 'html', 'code', 'list', 'image', 'audio', 'video', 'json',
            ])->default('markdown')->after('fields');

            // Model overrides
            $table->string('model_override', 100)->nullable()->after('output_type');
            $table->integer('max_tokens_override')->nullable()->after('model_override');

            // Access control (P13 — Part 35)
            $table->enum('access_level', [
                'inherit', 'public', 'login_required', 'free_plan', 'pro_plan',
            ])->default('inherit')->after('max_tokens_override');

            // Feature flags
            $table->boolean('is_featured')->default(false)->after('is_active');
            $table->boolean('requires_pro')->default(false)->after('is_featured');
            $table->boolean('supports_brand_voice')->default(true)->after('requires_pro');

            // Credit estimation (P15.8)
            $table->integer('avg_output_tokens')->nullable()->after('supports_brand_voice');

            // ─── SEO fields (P15.15) ────────────────────────────
            $table->string('meta_title', 255)->nullable()->after('usage_count');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->string('og_image', 500)->nullable()->after('meta_description');

            // ─── Page content (P15.14) ──────────────────────────
            $table->longText('about_content')->nullable()->after('og_image');
            $table->json('how_it_works')->nullable()->after('about_content');
            $table->json('usage_examples')->nullable()->after('how_it_works');
            $table->json('faq_items')->nullable()->after('usage_examples');

            // Section visibility toggles
            $table->boolean('show_about')->default(true)->after('faq_items');
            $table->boolean('show_how_it_works')->default(true)->after('show_about');
            $table->boolean('show_usage_examples')->default(true)->after('show_how_it_works');
            $table->boolean('show_faqs')->default(true)->after('show_usage_examples');
            $table->boolean('show_reviews')->default(true)->after('show_faqs');
            $table->boolean('show_related_tools')->default(true)->after('show_reviews');

            // Review cache columns (P15.14.5)
            $table->decimal('avg_rating', 3, 2)->default(0.00)->after('show_related_tools');
            $table->integer('review_count')->default(0)->after('avg_rating');

            // Index
            $table->index(['category_id', 'is_active']);
            $table->index('access_level');
        });

        // ─── 3. Tool Reviews (P15.14.5) ──────────────────────────
        Schema::create('tool_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('template_slug', 100);
            $table->foreign('template_slug')->references('slug')->on('ai_templates')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('rating');                  // 1–5
            $table->text('comment')->nullable();
            $table->text('admin_reply')->nullable();        // admin response
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->integer('helpful_count')->default(0);
            $table->timestamps();

            // One review per user per tool
            $table->unique(['user_id', 'template_slug']);
            $table->index(['template_slug', 'is_approved']);
            $table->index(['template_slug', 'rating']);
        });

        // ─── 4. Tool Review Votes (helpful/not helpful) ──────────
        Schema::create('tool_review_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('tool_reviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_helpful');                  // true = helpful, false = not helpful
            $table->timestamps();

            $table->unique(['review_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_review_votes');
        Schema::dropIfExists('tool_reviews');

        Schema::table('ai_templates', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id', 'is_active']);
            $table->dropIndex(['access_level']);
            $table->dropColumn([
                'prompt_system', 'prompt_user', 'category_id',
                'output_type', 'model_override', 'max_tokens_override',
                'access_level', 'is_featured', 'requires_pro', 'supports_brand_voice',
                'avg_output_tokens',
                'meta_title', 'meta_description', 'og_image',
                'about_content', 'how_it_works', 'usage_examples', 'faq_items',
                'show_about', 'show_how_it_works', 'show_usage_examples',
                'show_faqs', 'show_reviews', 'show_related_tools',
                'avg_rating', 'review_count',
            ]);
        });

        Schema::dropIfExists('ai_tool_categories');
    }
};
