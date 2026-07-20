<?php

declare(strict_types=1);

namespace Addons\AiAssistant\Services;

use Addons\AiAssistant\Models\AssistantConversation;
use Addons\AiAssistant\Models\AssistantMessage;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Owns the assistant's server-side conversation state.
 *
 * The controller never touches the tables directly — it asks this store to resolve the
 * thread for a (session, scope) pair, read prior turns as history, and record each side
 * of the exchange. Everything is guarded by Schema::hasTable so an install whose
 * conversation migration has not yet run silently degrades to stateless chat rather than
 * erroring — the persistence feature is additive, never load-bearing for a reply.
 */
class ConversationStore
{
    /**
     * How many past turns to feed back into the model. Keeps the prompt bounded regardless
     * of how long the stored thread grows.
     */
    private const HISTORY_LIMIT = 20;

    public function available(): bool
    {
        return Schema::hasTable('assistant_conversations') && Schema::hasTable('assistant_messages');
    }

    /**
     * The conversation for this surface + session, created on first contact. A guest's
     * user_id stays null; a signed-in user is stamped on (and back-filled if the thread
     * started while they were logged out and they since signed in).
     */
    public function resolve(string $sessionId, string $scope, ?User $user, ?string $contextPage, ?string $ipHash): ?AssistantConversation
    {
        if (! $this->available()) {
            return null;
        }

        $conversation = AssistantConversation::firstOrNew([
            'session_id' => $sessionId,
            'scope' => $scope,
        ]);

        if (! $conversation->exists) {
            $conversation->ulid = strtolower((string) Str::ulid());
            $conversation->ip_hash = $ipHash;
        }

        // Attach/refresh the owner and the page context on every touch.
        if ($user && ! $conversation->user_id) {
            $conversation->user_id = $user->id;
        }
        $conversation->context_page = $contextPage ?: $conversation->context_page;

        $conversation->save();

        return $conversation;
    }

    /**
     * Prior turns as a role-tagged array, oldest first — the shape the provider wants.
     * System messages are never persisted, so this only ever returns user/assistant turns.
     */
    public function history(?AssistantConversation $conversation): array
    {
        if (! $conversation) {
            return [];
        }

        return $conversation->messages()
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn (AssistantMessage $m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();
    }

    public function recordUserMessage(?AssistantConversation $conversation, string $content): ?AssistantMessage
    {
        if (! $conversation) {
            return null;
        }

        $message = $conversation->messages()->create([
            'role' => 'user',
            'content' => $content,
        ]);

        // The first thing the user says makes a decent default title.
        if (! $conversation->title) {
            $conversation->title = Str::limit(trim($content), 60);
        }

        $conversation->message_count = ($conversation->message_count ?? 0) + 1;
        $conversation->last_message_at = now();
        $conversation->save();

        return $message;
    }

    /**
     * Persist an assistant reply and roll its usage up onto the conversation. content_hash
     * is what the feedback screen later joins a rating back to.
     */
    public function recordAssistantMessage(
        ?AssistantConversation $conversation,
        string $content,
        string $model,
        int $inputTokens,
        int $outputTokens,
        float $credits,
        array $sources = [],
    ): ?AssistantMessage {
        if (! $conversation || $content === '') {
            return null;
        }

        $message = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $content,
            'content_hash' => sha1($content),
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'credits_charged' => $credits,
            'sources' => $sources ?: null,
        ]);

        $conversation->message_count = ($conversation->message_count ?? 0) + 1;
        $conversation->input_tokens = ($conversation->input_tokens ?? 0) + $inputTokens;
        $conversation->output_tokens = ($conversation->output_tokens ?? 0) + $outputTokens;
        $conversation->total_credits = (float) ($conversation->total_credits ?? 0) + $credits;
        $conversation->model = $model;
        $conversation->last_message_at = now();
        $conversation->save();

        return $message;
    }

    /**
     * The stored thread for a session, shaped for the client to restore on open. Scoped by
     * session AND (when signed in) by owner, so a recycled guest session id can never hand
     * one user another's history.
     */
    public function transcript(string $sessionId, string $scope, ?User $user): array
    {
        if (! $this->available()) {
            return [];
        }

        $conversation = AssistantConversation::where('session_id', $sessionId)
            ->where('scope', $scope)
            ->when($user, fn ($q) => $q->where(function ($w) use ($user) {
                $w->whereNull('user_id')->orWhere('user_id', $user->id);
            }))
            ->first();

        if (! $conversation) {
            return [];
        }

        return $conversation->messages()
            ->orderBy('id')
            ->get(['role', 'content', 'content_hash', 'sources', 'created_at'])
            ->map(fn (AssistantMessage $m) => [
                'role' => $m->role,
                'content' => $m->content,
                'hash' => $m->content_hash,
                'sources' => $m->sources ?? [],
                'created_at' => $m->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * A signed-in user's past conversations, newest first — the data behind the chat
     * history list.
     *
     * Only ever returns threads OWNED by this user. Guests get nothing: their threads have
     * no owner, and listing by IP would hand one visitor another's chat on a shared address
     * (office NAT, café wifi). That's also the honest reading of MagicAI's own model —
     * "Temporary Chat — Login to save your chat history".
     */
    public function listForUser(?User $user, string $scope, int $limit = 30): array
    {
        if (! $this->available() || ! $user) {
            return [];
        }

        return AssistantConversation::query()
            ->where('user_id', $user->id)
            ->where('scope', $scope)
            ->where('message_count', '>', 0)
            // id breaks the tie when two threads share a last_message_at second, so the
            // order is always deterministic rather than whatever the DB happens to return.
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['session_id', 'title', 'message_count', 'last_message_at'])
            ->map(fn (AssistantConversation $c) => [
                'session_id' => $c->session_id,
                'title' => $c->title ?: 'New conversation',
                'message_count' => $c->message_count,
                'last_message_at' => $c->last_message_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Delete one of the user's own conversations. Ownership is part of the query, not a
     * check before it, so there is no window in which someone else's thread could be hit.
     */
    public function deleteForUser(?User $user, string $sessionId, string $scope): bool
    {
        if (! $this->available() || ! $user) {
            return false;
        }

        return AssistantConversation::query()
            ->where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->where('scope', $scope)
            ->delete() > 0; // messages cascade
    }
}
