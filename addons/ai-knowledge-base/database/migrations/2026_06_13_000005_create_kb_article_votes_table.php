<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_article_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kb_article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->string('session_id', 64);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->tinyInteger('vote');
            $table->timestamps();

            $table->unique(['kb_article_id', 'session_id']);
            $table->index(['kb_article_id', 'vote']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_article_votes');
    }
};
