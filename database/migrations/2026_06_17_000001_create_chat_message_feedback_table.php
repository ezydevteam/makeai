<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_message_feedback', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->constrained('conversation_messages')->cascadeOnDelete();
            $table->tinyInteger('rating'); // 1 = like, -1 = dislike
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'message_id']);
            $table->index(['conversation_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_feedback');
    }
};
