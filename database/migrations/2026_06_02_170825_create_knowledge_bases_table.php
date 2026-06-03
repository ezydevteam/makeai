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
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('knowledge_base_documents', function (Blueprint $table) {
            $table->id();
            $table->string('knowledge_base_id');
            $table->unsignedBigInteger('user_id');
            $table->string('filename');
            $table->unsignedBigInteger('filesize')->nullable();
            $table->unsignedInteger('char_count')->default(0);
            $table->unsignedInteger('chunk_count')->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index('knowledge_base_id');
            $table->index('user_id');
        });

        Schema::create('knowledge_base_chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedInteger('chunk_index');
            $table->text('text');
            $table->unsignedInteger('char_start');
            $table->unsignedInteger('char_end');
            $table->timestamps();

            $table->index('document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_chunks');
        Schema::dropIfExists('knowledge_base_documents');
        Schema::dropIfExists('knowledge_bases');
    }
};
