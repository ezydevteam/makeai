<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_id', 64)->index();
            $table->string('context_page', 255)->nullable();
            $table->string('message_hash', 64);
            $table->tinyInteger('rating')->comment('1=up, -1=down');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'message_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_feedback');
    }
};
