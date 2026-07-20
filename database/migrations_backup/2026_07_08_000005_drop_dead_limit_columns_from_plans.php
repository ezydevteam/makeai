<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove dead plan columns. The app is credit-based: a plan's only usage lever is
 * `credits` (granted per period), and spend rate is capped by the per-user / guest /
 * global controls in AI Settings. These token/image/chat/model columns were never
 * enforced anywhere and were not even editable in the plan editor — they only created
 * a false impression that a token-limit system existed. Dropping them makes the plan
 * model honest and simple for non-technical buyers.
 */
return new class extends Migration
{
    private array $columns = [
        'ai_models', 'max_tokens_per_request', 'daily_token_limit',
        'max_images_per_day', 'max_chats',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                if (Schema::hasColumn('plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('plans')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'ai_models')) {
                $table->json('ai_models')->nullable();
            }
            if (! Schema::hasColumn('plans', 'max_tokens_per_request')) {
                $table->integer('max_tokens_per_request')->default(4096);
            }
            if (! Schema::hasColumn('plans', 'daily_token_limit')) {
                $table->integer('daily_token_limit')->default(50000);
            }
            if (! Schema::hasColumn('plans', 'max_images_per_day')) {
                $table->integer('max_images_per_day')->default(10);
            }
            if (! Schema::hasColumn('plans', 'max_chats')) {
                $table->integer('max_chats')->default(50);
            }
        });
    }
};
