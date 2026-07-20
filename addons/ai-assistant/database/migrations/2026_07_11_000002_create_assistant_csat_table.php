<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Experience ratings (CSAT) left from the chat panel's "How was your experience?" card.
 *
 * Kept separate from per-message thumbs feedback (ai_assistant_feedback): that rates a
 * single answer, this rates the session overall on a 1–5 scale. One score per session is
 * enough — the unique key lets a later rating replace an earlier one rather than pile up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_csat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 64);
            $table->enum('scope', ['user', 'admin'])->default('user');
            $table->unsignedTinyInteger('score'); // 1 (Bad) … 5 (Very Good)
            $table->string('context_page')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'scope']);
            $table->index('score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_csat');
    }
};
