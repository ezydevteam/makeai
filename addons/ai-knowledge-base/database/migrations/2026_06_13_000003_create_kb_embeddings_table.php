<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vector store using MySQL JSON column for float[] embeddings.
     * PHP cosine similarity computed over top-N candidates — no pgvector required.
     * Works on shared hosting for typical help centers (<500 articles, <5000 chunks).
     */
    public function up(): void
    {
        Schema::create('kb_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kb_article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->unsignedSmallInteger('chunk_index');
            $table->text('chunk_text');
            $table->json('embedding');
            $table->unsignedSmallInteger('token_count')->default(0);
            $table->timestamps();

            $table->index('kb_article_id');
            $table->unique(['kb_article_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_embeddings');
    }
};
