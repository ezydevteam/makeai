<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tool Page Features:
     * - tool_slug_history: 301 redirect support for renamed slugs
     * - tool_page_views: hourly-aggregated view counts (flushed from Redis)
     * - ai_tools.views_count: denormalized view counter
     */
    public function up(): void
    {
        // ─── Slug History — tracks renamed tool slugs for 301 redirects ───
        if (! Schema::hasTable('tool_slug_history')) {
            Schema::create('tool_slug_history', function (Blueprint $table) {
                $table->id();
                $table->string('old_slug', 200);
                $table->string('new_slug', 200);
                $table->string('model_type', 50);  // 'ai_tool' or 'category'
                $table->timestamp('changed_at')->useCurrent();
            });
        }

        // ─── Tool Page Views — hourly aggregated from Redis counters ───
        if (! Schema::hasTable('tool_page_views')) {
            Schema::create('tool_page_views', function (Blueprint $table) {
                $table->id();
                $table->string('tool_slug', 200);
                $table->date('viewed_date');
                $table->unsignedBigInteger('views_count')->default(0);

                $table->unique(['tool_slug', 'viewed_date']);
                $table->index(['viewed_date', 'views_count']);
            });
        }

        // ─── Denormalized views_count on ai_tools ───
        if (! Schema::hasColumn('ai_tools', 'views_count')) {
            Schema::table('ai_tools', function (Blueprint $table) {
                $table->unsignedBigInteger('views_count')->default(0)->after('usage_count');
            });
        }

        // ─── Category slug history support ───
        if (! Schema::hasTable('category_slug_history')) {
            Schema::create('category_slug_history', function (Blueprint $table) {
                $table->id();
                $table->string('old_slug', 200);
                $table->string('new_slug', 200);
                $table->timestamp('changed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_page_views');
        Schema::dropIfExists('tool_slug_history');
        Schema::dropIfExists('category_slug_history');

        if (Schema::hasColumn('ai_tools', 'views_count')) {
            Schema::table('ai_tools', function (Blueprint $table) {
                $table->dropColumn('views_count');
            });
        }
    }
};
