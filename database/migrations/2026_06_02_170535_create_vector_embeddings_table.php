<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vector_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('knowledge_base_id');
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('chunk_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('embedding');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('knowledge_base_id');
            $table->index('document_id');
            $table->index('chunk_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vector_embeddings');
    }
};
