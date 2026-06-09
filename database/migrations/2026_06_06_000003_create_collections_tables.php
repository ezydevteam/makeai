<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_collections', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->tinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('user_collection_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('user_collections')->cascadeOnDelete();
            $table->string('tool_slug', 100);
            $table->tinyInteger('sort_order')->default(0);
            $table->timestamp('added_at')->useCurrent();
            $table->unique(['collection_id', 'tool_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_collection_tools');
        Schema::dropIfExists('user_collections');
    }
};
