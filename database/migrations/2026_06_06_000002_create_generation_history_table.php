<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_history', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tool_slug', 100);
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->text('prompt_system');
            $table->text('prompt_user');
            $table->json('field_values');
            $table->string('model', 100);
            $table->string('provider', 50);
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->integer('max_tokens')->default(0);
            $table->text('output_preview');
            $table->integer('tokens_input')->default(0);
            $table->integer('tokens_output')->default(0);
            $table->boolean('is_favorited')->default(false);
            $table->string('label', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'tool_slug', 'created_at']);
            $table->index(['user_id', 'is_favorited']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_history');
    }
};
