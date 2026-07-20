<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tool generation source. A tool runs on an external integration, on the LLM,
 * or on the integration with an LLM fallback when the integration isn't
 * configured/enabled (or errors). `integration_slug` names the backing
 * integration from config/external-tools.php.
 *
 * The LLM fallback model is the tool's own `model_override` when set, else the
 * global `default_ai_model` (resolved at runtime, not stored here).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_tools', function (Blueprint $table) {
            // llm | integration | integration_llm_fallback
            $table->string('generation_mode', 32)->default('llm')->after('model_override');
            $table->string('integration_slug', 60)->nullable()->after('generation_mode');
        });
    }

    public function down(): void
    {
        Schema::table('ai_tools', function (Blueprint $table) {
            $table->dropColumn(['generation_mode', 'integration_slug']);
        });
    }
};
