<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content');
            $table->string('tool_slug', 100)->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedBigInteger('folder_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('tool_slug');
        });

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');

        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
