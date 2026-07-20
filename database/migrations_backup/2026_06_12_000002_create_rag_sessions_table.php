<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rag_sessions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('tool_slug', 100);
            $table->unsignedBigInteger('knowledge_base_id');
            $table->string('title')->nullable();
            $table->json('source_meta')->nullable();
            $table->string('status')->default('ingesting');
            $table->string('ingest_error', 500)->nullable();
            $table->boolean('saved_to_kb')->default(false);
            $table->char('share_token', 26)->nullable()->unique();
            $table->timestamps();

            $table->index(['user_id', 'tool_slug']);
            $table->index(['status', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('rag_messages', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('session_id', 26);
            $table->string('role');
            $table->longText('content');
            $table->json('sources')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('credits_used', 10, 4)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['session_id', 'created_at']);
            $table->foreign('session_id')->references('id')->on('rag_sessions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_messages');
        Schema::dropIfExists('rag_sessions');
    }
};
