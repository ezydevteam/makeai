<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['comment_id', 'user_id']);
            $table->unique(['comment_id', 'ip_hash']);
        });

        Schema::create('comment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->string('reason', 100)->nullable();
            $table->text('details')->nullable();
            $table->enum('status', ['open', 'reviewed', 'dismissed'])->default('open');
            $table->timestamps();

            $table->unique(['comment_id', 'user_id']);
            $table->unique(['comment_id', 'ip_hash']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_reports');
        Schema::dropIfExists('comment_likes');
    }
};
