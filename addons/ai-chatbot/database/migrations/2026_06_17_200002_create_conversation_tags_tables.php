<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('color', 7)->default('#3B82F6');
            $table->timestamps();
            
            $table->unique(['user_id', 'name']);
        });

        Schema::create('conversation_tag', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_tag_id')->constrained('conversation_tags')->cascadeOnDelete();
            $table->primary(['conversation_id', 'conversation_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_tag');
        Schema::dropIfExists('conversation_tags');
    }
};
