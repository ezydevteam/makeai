<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_searches', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('query', 500);
            $table->unsignedSmallInteger('results_count')->default(0);
            $table->boolean('was_answered')->default(false);
            $table->json('article_ids')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
            $table->index('was_answered');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_searches');
    }
};
