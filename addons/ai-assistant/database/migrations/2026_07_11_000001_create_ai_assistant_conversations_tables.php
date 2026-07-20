<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side conversation persistence for the assistant.
 *
 * Until now history lived only in the browser's localStorage and was posted back to the
 * server on every turn — untrusted, unqueryable, and lost when the tab closed. These
 * tables make the server the source of truth: the client sends only its new message plus
 * a session id, and the server rebuilds context from what it actually stored.
 *
 * Guests are first-class: user_id is nullable and a conversation is identified by its
 * session id, so a signed-out visitor still gets a coherent multi-turn thread.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->char('ulid', 26)->unique();

            // Nullable for guests; the thread is keyed by session_id in that case.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 64);

            // A conversation belongs to exactly one surface. The admin widget and the
            // public widget must never share a thread even if session ids collided.
            $table->enum('scope', ['user', 'admin'])->default('user');

            // sha1(ip) — lets an operator trace guest abuse without storing raw IPs.
            $table->char('ip_hash', 40)->nullable();

            $table->string('title')->nullable();
            $table->string('model', 150)->nullable();
            $table->string('context_page')->nullable();

            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->decimal('total_credits', 12, 4)->default(0);
            $table->integer('message_count')->default(0);
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            // The hot lookup: "the conversation for this session on this surface".
            $table->unique(['session_id', 'scope']);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('assistant_conversations')
                ->cascadeOnDelete();

            $table->enum('role', ['user', 'assistant', 'system']);
            $table->longText('content');

            // sha1(content) of an assistant reply. Feedback is posted with a hash of the
            // rendered answer, so this lets the admin feedback screen join a rating back to
            // the exact message it was about — the thing that was impossible when messages
            // were never stored.
            $table->char('content_hash', 40)->nullable();

            $table->string('model', 150)->nullable();
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->decimal('credits_charged', 12, 4)->default(0);

            // Knowledge-base citations attached to a grounded answer.
            $table->json('sources')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'created_at']);
            $table->index('content_hash');
        });

        // Link the existing (previously write-only) feedback table to the message it rates.
        if (Schema::hasTable('ai_assistant_feedback')) {
            Schema::table('ai_assistant_feedback', function (Blueprint $table) {
                $table->foreignId('message_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('assistant_messages')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_assistant_feedback') && Schema::hasColumn('ai_assistant_feedback', 'message_id')) {
            Schema::table('ai_assistant_feedback', function (Blueprint $table) {
                $table->dropConstrainedForeignId('message_id');
            });
        }

        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_conversations');
    }
};
