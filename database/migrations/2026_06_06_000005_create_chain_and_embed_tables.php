<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_chains', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->json('steps');
            $table->timestamp('last_run_at')->nullable();
            $table->integer('run_count')->default(0);
            $table->timestamps();
        });

        Schema::create('tool_chain_runs', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('chain_id')->constrained('tool_chains')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('running');
            $table->json('step_outputs')->nullable();
            $table->integer('total_tokens')->default(0);
            $table->decimal('total_credits', 12, 4)->default(0);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // Embed management table
        Schema::create('tool_embeds', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tool_slug', 100);
            $table->string('token', 64)->unique();
            $table->string('label', 100)->nullable();
            $table->json('allowed_origins')->nullable();
            $table->string('password_hash', 255)->nullable();
            $table->string('theme', 10)->default('auto');
            $table->string('primary_color', 7)->nullable();
            $table->boolean('show_branding')->default(true);
            $table->integer('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'tool_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_chain_runs');
        Schema::dropIfExists('tool_chains');
        Schema::dropIfExists('tool_embeds');
    }
};
