<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_bases', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_ephemeral')->default(false);
            $table->string('source_tool', 100)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
        });

        Schema::create('knowledge_base_documents', function (Blueprint $table) {
            $table->id();
            $table->string('knowledge_base_id');
            $table->bigInteger('user_id')->unsigned();
            $table->string('filename');
            $table->bigInteger('filesize')->unsigned()->nullable();
            $table->integer('char_count')->unsigned()->default(0);
            $table->integer('chunk_count')->unsigned()->default(0);
            $table->string('status')->default('pending');
            $table->softDeletes();
            $table->timestamps();

            $table->index('knowledge_base_id');
            $table->index('user_id');
        });

        Schema::create('knowledge_base_chunks', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('document_id')->unsigned();
            $table->integer('chunk_index')->unsigned();
            $table->text('text');
            $table->integer('char_start')->unsigned();
            $table->integer('char_end')->unsigned();
            $table->timestamps();

            $table->index('document_id');
            // Fulltext indexes are MySQL-only; sqlite (used in tests) can't build them and the
            // app falls back to LIKE search there. Guard so migrations stay sqlite-safe.
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText('text', 'chunks_text_fulltext');
            }
        });

        Schema::create('vector_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('knowledge_base_id');
            $table->bigInteger('document_id')->unsigned();
            $table->bigInteger('chunk_id')->unsigned();
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->json('embedding');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('knowledge_base_id');
            $table->index('document_id');
            $table->index('chunk_id');
        });

        Schema::create('rag_sessions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->bigInteger('user_id')->unsigned();
            $table->string('tool_slug', 100);
            $table->bigInteger('knowledge_base_id')->unsigned();
            $table->string('title')->nullable();
            $table->json('source_meta')->nullable();
            $table->string('status')->default('ingesting');
            $table->string('ingest_stage', 50)->nullable();
            $table->string('ingest_error', 500)->nullable();
            $table->boolean('saved_to_kb')->default(false);
            $table->char('share_token', 26)->nullable()->unique();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'tool_slug']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('rag_messages', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('session_id', 26);
            $table->string('role');
            $table->longText('content');
            $table->json('sources')->nullable();
            $table->integer('input_tokens')->unsigned()->nullable();
            $table->integer('output_tokens')->unsigned()->nullable();
            $table->decimal('credits_used', 10, 4)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('session_id')->references('id')->on('rag_sessions')->onDelete('cascade');
            $table->index(['session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rag_messages');
        Schema::dropIfExists('rag_sessions');
        Schema::dropIfExists('vector_embeddings');
        Schema::dropIfExists('knowledge_base_chunks');
        Schema::dropIfExists('knowledge_base_documents');
        Schema::dropIfExists('knowledge_bases');
    }
};
