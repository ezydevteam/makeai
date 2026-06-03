<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * LAYER 3 — AI CORE alignment (updated for ai_tools + categories refactor).
     *
     * On fresh installs: creates tool_reviews + tool_review_votes tables.
     * Other structural changes (categories, ai_tools columns) are in the base migration.
     */
    public function up(): void
    {
        // Determine the actual tool table name (ai_tools on fresh, ai_templates on legacy)
        $toolsTable = Schema::hasTable('ai_tools') ? 'ai_tools' : 'ai_templates';
        $reviewSlugCol = Schema::hasTable('ai_tools') ? 'tool_slug' : 'template_slug';

        // ─── Tool Reviews ────────────────────────────────────────
        if (! Schema::hasTable('tool_reviews')) {
            Schema::create('tool_reviews', function (Blueprint $table) use ($toolsTable, $reviewSlugCol) {
                $table->id();
                $table->string($reviewSlugCol, 100);
                $table->foreign($reviewSlugCol)->references('slug')->on($toolsTable)->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->tinyInteger('rating');
                $table->text('comment')->nullable();
                $table->text('admin_reply')->nullable();
                $table->boolean('is_approved')->default(false);
                $table->boolean('is_featured')->default(false);
                $table->integer('helpful_count')->default(0);
                $table->timestamps();

                $table->unique(['user_id', $reviewSlugCol]);
                $table->index([$reviewSlugCol, 'is_approved']);
                $table->index([$reviewSlugCol, 'rating']);
            });
        }

        // ─── Tool Review Votes ───────────────────────────────────
        if (! Schema::hasTable('tool_review_votes')) {
            Schema::create('tool_review_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('review_id')->constrained('tool_reviews')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->boolean('is_helpful');
                $table->timestamps();

                $table->unique(['review_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_review_votes');
        Schema::dropIfExists('tool_reviews');
    }
};
