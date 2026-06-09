<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_output_ratings', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tool_slug', 100);
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('generation_history_id')->nullable()->constrained('generation_history')->nullOnDelete();
            $table->tinyInteger('rating');
            $table->string('feedback_text', 500)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('provider', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tool_slug', 'rating', 'created_at']);
            $table->index('user_id');
            $table->unique(['user_id', 'generation_history_id'], 'uq_user_gen_rating');
        });

        // Add preferences JSON column to users
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'preferences')) {
                $table->json('preferences')->nullable()->after('brand_voice');
            }
        });

        // Add max_variants to ai_tools
        Schema::table('ai_tools', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_tools', 'max_variants')) {
                $table->tinyInteger('max_variants')->default(3)->after('temperature');
            }
        });

        // Add is_embeddable to ai_tools
        Schema::table('ai_tools', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_tools', 'is_embeddable')) {
                $table->boolean('is_embeddable')->default(true)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_output_ratings');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferences');
        });
        Schema::table('ai_tools', function (Blueprint $table) {
            $table->dropColumn(['max_variants', 'is_embeddable']);
        });
    }
};
