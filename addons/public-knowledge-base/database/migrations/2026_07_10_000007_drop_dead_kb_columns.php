<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove two dead columns:
 *  - kb_categories.articles_count — a denormalized counter written only by the seeder
 *    and never maintained; every runtime read uses withCount() instead, so the column
 *    was always stale. Keeping it invites bugs.
 *  - kb_embeddings.token_count — populated during ingestion but never read anywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('kb_categories', 'articles_count')) {
            Schema::table('kb_categories', function (Blueprint $table) {
                $table->dropColumn('articles_count');
            });
        }

        if (Schema::hasColumn('kb_embeddings', 'token_count')) {
            Schema::table('kb_embeddings', function (Blueprint $table) {
                $table->dropColumn('token_count');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('kb_categories', 'articles_count')) {
            Schema::table('kb_categories', function (Blueprint $table) {
                $table->unsignedInteger('articles_count')->default(0);
            });
        }

        if (! Schema::hasColumn('kb_embeddings', 'token_count')) {
            Schema::table('kb_embeddings', function (Blueprint $table) {
                $table->unsignedSmallInteger('token_count')->default(0);
            });
        }
    }
};
