<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop verified dead / inert AI-tools columns (audit: no runtime readers).
 *
 *  ai_tools.avg_latency_ms   — displayed but NEVER written by any code (always null)
 *  ai_tools.credit_cost      — reader (TokenGuard::chargeToolCredits) had no production
 *                              caller; non-LLM billing uses settings external_* fixed cost
 *  ai_tools.show_regenerate  — payload-only toggle; frontend renders the control anyway
 *  ai_tools.show_editor      — same
 *  ai_tools.requires_pro     — the pro/plan requirement is derived from access_level via
 *                              isProRequired(); the raw column drove only a badge (now
 *                              also derived), so it could drift from real enforcement
 *  tool_chain_runs.total_credits      — created, never updated (job tracks total_tokens)
 *  tool_reviews.is_featured           — scope existed but was never called; nothing reads it
 *  user_collection_tools.added_at     — never read (ordering uses sort_order)
 *  ai_chats.category                  — defaulted 'general', never read/written by app
 *  ai_output_ratings.document_id      — write-only; dedup/queries use generation_history_id
 *  documents.folder_id                — half-built folders feature; no folders table, never
 *                                       grouped/filtered
 */
return new class extends Migration
{
    private array $map = [
        'ai_tools' => ['avg_latency_ms', 'credit_cost', 'show_regenerate', 'show_editor', 'requires_pro'],
        'tool_chain_runs' => ['total_credits'],
        'tool_reviews' => ['is_featured'],
        'user_collection_tools' => ['added_at'],
        'ai_chats' => ['category'],
        'ai_output_ratings' => ['document_id'],
        'documents' => ['folder_id'],
    ];

    public function up(): void
    {
        // These two columns carry a foreign-key constraint that must be dropped
        // before the column itself. dropForeign is wrapped since the constraint may
        // be absent on some installs (e.g. sqlite / already dropped).
        foreach (['ai_output_ratings' => 'document_id', 'documents' => 'folder_id'] as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                try {
                    Schema::table($table, fn (Blueprint $t) => $t->dropForeign([$column]));
                } catch (\Throwable $e) {
                    // No FK to drop — safe to continue to the column drop below.
                }
            }
        }

        foreach ($this->map as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $present = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));
            if ($present === []) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($present) {
                $t->dropColumn($present);
            });
        }
    }

    public function down(): void
    {
        // Re-add with the original definitions (best-effort restore).
        $restore = [
            'ai_tools' => function (Blueprint $t) {
                $t->unsignedInteger('avg_latency_ms')->nullable();
                $t->decimal('credit_cost', 10, 2)->nullable();
                $t->boolean('show_regenerate')->default(true);
                $t->boolean('show_editor')->default(true);
                $t->boolean('requires_pro')->default(false);
            },
            'tool_chain_runs' => fn (Blueprint $t) => $t->decimal('total_credits', 12, 4)->default(0),
            'tool_reviews' => fn (Blueprint $t) => $t->boolean('is_featured')->default(false),
            'user_collection_tools' => fn (Blueprint $t) => $t->timestamp('added_at')->nullable(),
            'ai_chats' => fn (Blueprint $t) => $t->string('category')->default('general'),
            'ai_output_ratings' => fn (Blueprint $t) => $t->unsignedBigInteger('document_id')->nullable(),
            'documents' => fn (Blueprint $t) => $t->unsignedBigInteger('folder_id')->nullable(),
        ];

        foreach ($restore as $table => $cb) {
            if (Schema::hasTable($table)) {
                Schema::table($table, $cb);
            }
        }
    }
};
