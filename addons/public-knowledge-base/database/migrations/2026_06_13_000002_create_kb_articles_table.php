<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('kb_category_id')->nullable()->constrained('kb_categories')->nullOnDelete();
            $table->string('title', 255);
            $table->string('slug', 255)->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('body');
            $table->longText('body_plain')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->smallInteger('sort_order')->default(0);
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('not_helpful_count')->default(0);
            $table->enum('embed_status', ['pending', 'processing', 'done', 'failed'])->default('pending');
            $table->text('embed_error')->nullable();
            $table->timestamp('embedded_at')->nullable();
            $table->string('meta_title', 160)->nullable();
            $table->string('meta_desc', 320)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['kb_category_id', 'status']);
            $table->index(['embed_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_articles');
    }
};
