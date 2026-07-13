<?php

namespace Addons\AiAssistant\Controllers;

use Addons\AiAssistant\Models\AiAssistantFeedback;
use Addons\AiAssistant\Models\AssistantConversation;
use Addons\AiAssistant\Services\AiAssistantService;
use Addons\AiAssistant\Services\ConversationStore;
use Addons\AiAssistant\Services\SlashCommandRegistry;
use Addons\AiAssistant\Services\SlashCommandResult;
use Addons\AiAssistant\Support\KnowledgeBase;
use Addons\AiAssistant\Support\ProductDocs;
use App\Models\User;
use App\Services\AI\ProviderRegistry;
use App\Services\AI\TokenGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiAssistantController extends Controller
{
    public function __construct(
        private AiAssistantService $assistantService,
        private SlashCommandRegistry $commands,
        private ConversationStore $conversations,
    ) {}

    /**
     * Frontend chat. Open to guests when the admin has set show_to = all.
     */
    public function chat(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array|max:20',
            'history.*.role' => 'required|in:user,assistant',
            'history.*.content' => 'required|string',
            'session_id' => 'required|string|max:64',
            'context_page' => 'nullable|string|max:255',
        ]);

        /** @var User|null $user */
        $user = $request->user();

        // The admin buyer's access decision, enforced server-side. The widget also hides
        // itself, but a hand-rolled POST must not get past this.
        if (! $this->assistantService->isVisibleForUser($user, false)) {
            return $this->errorStream('unavailable', 'The AI Assistant is not available.');
        }

        return $this->handleChat(
            request: $request,
            validated: $validated,
            user: $user,
            billingUser: $user,
            scope: SlashCommandRegistry::SCOPE_USER,
            page: $validated['context_page'] ?? '',
            systemPrompt: $this->assistantService->buildFrontendSystemPrompt($user, $validated['context_page'] ?? ''),
            usageType: 'assistant_chat',
        );
    }

    /**
     * Admin-panel chat.
     *
     * Billed to the internal system user (User::internalAi()), exactly as the other admin
     * AI-assist features are. That account bypasses per-user credit limits but is STILL
     * subject to the global daily AI budget kill-switch — which the previous version of
     * this method skipped entirely by never calling TokenGuard::before().
     */
    public function adminChat(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:4000',
            'history' => 'nullable|array|max:30',
            'history.*.role' => 'required|in:user,assistant',
            'history.*.content' => 'required|string',
            'session_id' => 'required|string|max:64',
        ]);

        if (! $this->assistantService->isVisibleForUser(null, true)) {
            return $this->errorStream('unavailable', 'The AI Assistant is not available in the admin panel.');
        }

        return $this->handleChat(
            request: $request,
            validated: $validated,
            user: null,
            billingUser: User::internalAi(),
            scope: SlashCommandRegistry::SCOPE_ADMIN,
            page: 'Admin Panel',
            systemPrompt: $this->assistantService->buildAdminSystemPrompt(),
            usageType: 'assistant_admin_chat',
        );
    }

    /**
     * Shared pipeline for both surfaces: slash commands → automation rules → daily limit
     * → grounded model call. Ordering matters and is load-bearing (see comments below).
     *
     * @param  User|null  $user  the real end user, or null for guests/admin surface
     * @param  User|null  $billingUser  the account TokenGuard meters against
     */
    private function handleChat(
        Request $request,
        array $validated,
        ?User $user,
        ?User $billingUser,
        string $scope,
        string $page,
        string $systemPrompt,
        string $usageType,
    ): StreamedResponse {
        $message = $validated['message'];
        $sessionId = $validated['session_id'];
        $sources = [];
        $grounded = false;

        // 1. Slash commands. A direct-reply command answers from local data — it never
        //    reaches a provider, so it costs nothing and is not rate limited. It is also
        //    NOT persisted: /usage, /credits and friends are ephemeral lookups, not part of
        //    the conversation the user is actually having.
        if ($parsed = $this->commands->parse($message)) {
            $result = $this->commands->handle($parsed['name'], $parsed['args'], $scope, $user);

            if ($result instanceof SlashCommandResult) {
                if ($result->isDirectReply()) {
                    return $this->textStream($result->reply);
                }

                // A grounding command (e.g. /docs) still spends, so it falls through to the
                // limit check below with the retrieved context folded in.
                $systemPrompt .= $result->systemAppend;
                $message = $result->message;
                $sources = $result->sources;
                $grounded = true;
            }
            // An unknown or out-of-scope command falls through and is treated as ordinary
            // chat, so the frontend cannot probe for the existence of admin commands.
        }

        // 2. The daily limit is checked BEFORE automation rules. Previously a rule-matched
        //    message returned early, incrementing the counter but never checking it — so
        //    canned replies both bypassed the cap and consumed quota against it.
        if (! $this->assistantService->checkDailyLimit($user, $sessionId)) {
            return $this->errorStream(
                'daily_limit_reached',
                'You have reached your message limit for today. Please try again tomorrow.'
            );
        }

        // 3. Automation rules: a canned reply, no provider call. Persisted, because from the
        //    user's point of view it IS the assistant answering their question.
        if ($rule = $this->assistantService->findMatchingAutomationRule($message)) {
            $this->assistantService->incrementDailyCount($user, $sessionId);

            $reply = $this->assistantService->interpolateVariables($rule->response, $user, $page);

            $conversation = $this->resolveConversation($request, $validated, $user, $scope);
            $this->conversations->recordUserMessage($conversation, $message);
            $this->conversations->recordAssistantMessage($conversation, $reply, 'automation-rule', 0, 0, 0.0);

            return $this->textStream($reply);
        }

        [$providerName, $modelName] = $this->resolveModel();

        // 4. Pre-flight credit/quota/budget check, OUTSIDE the stream callback so a refusal
        //    is a clean frame rather than a half-open stream. TokenGuard is mode-aware: in
        //    quota mode it meters the resetting allowance, in metered mode it gates on the
        //    wallet. Guests ($billingUser === null) are metered per-IP by TokenGuard itself.
        try {
            TokenGuard::before($billingUser, null, $modelName);
        } catch (\Throwable $e) {
            return $this->errorStream('billing', $e->getMessage());
        }

        $this->assistantService->incrementDailyCount($user, $sessionId);

        // 5. Persist the exchange and rebuild context from what WE stored — never from the
        //    client-supplied `history`, which was fully spoofable. The conversation is the
        //    single source of truth for multi-turn context now.
        $conversation = $this->resolveConversation($request, $validated, $user, $scope);
        $history = $this->conversations->history($conversation);
        $this->conversations->recordUserMessage($conversation, $message);

        // 6. Auto-ground (unless a /docs command already did) — on the corpus that actually
        //    serves this audience. The admin panel gets the MakeAI product documentation;
        //    the public widget gets the buyer's own Knowledge Base.
        //
        //    Previously BOTH surfaces grounded on the KB, which meant an admin asking "how
        //    do I add a provider key?" was answered from the help centre the buyer wrote for
        //    their customers — and, on the many installs without the KB addon, from nothing
        //    at all. The admin assistant had no product knowledge and hallucinated the UI.
        if (! $grounded) {
            if ($scope === SlashCommandRegistry::SCOPE_ADMIN) {
                $this->groundOnProductDocs($message, $systemPrompt, $sources);
            } else {
                $this->groundOnKnowledgeBase($message, $systemPrompt, $sources);
            }
        }

        $messages = $this->buildMessages($systemPrompt, $history, $message);

        return $this->streamCompletion(
            $messages, $providerName, $modelName, $billingUser, $usageType, $sources, $conversation
        );
    }

    /**
     * Fold Knowledge Base context into the system prompt when the admin has the feature on
     * and the KB addon is available. Best-effort: a KB miss or failure leaves the prompt
     * untouched and the assistant answers ungrounded rather than erroring.
     */
    private function groundOnKnowledgeBase(string $message, string &$systemPrompt, array &$sources): void
    {
        $hit = KnowledgeBase::context($message, 5);

        if (($hit['context'] ?? '') === '') {
            return;
        }

        $systemPrompt .= "\n\n--- Knowledge Base ---\n"
            . "Use the excerpts below to answer when they are relevant. If they do not cover "
            . "the question, answer from general knowledge and do not invent site-specific facts.\n\n"
            . $hit['context'];

        $sources = $this->citableKbSources($hit['sources'] ?? []);
    }

    /**
     * Fold the MakeAI product documentation into the ADMIN system prompt.
     *
     * The instruction is stricter than the KB's. The admin is asking operational questions
     * about software they are administering, and a plausible-sounding invented settings path
     * is worse than an admission of ignorance — they will go looking for a screen that does
     * not exist. So the model is told to say it doesn't know rather than improvise.
     */
    private function groundOnProductDocs(string $message, string &$systemPrompt, array &$sources): void
    {
        $hit = ProductDocs::context($message, 5);

        if (($hit['context'] ?? '') === '') {
            return;
        }

        $systemPrompt .= "\n\n--- MakeAI Documentation ---\n"
            . "The excerpts below are from the official MakeAI documentation for the exact "
            . "version this site is running. Prefer them over your own knowledge of similar "
            . "products. If they do not answer the question, say so plainly — do NOT invent "
            . "admin screens, settings names or menu paths.\n\n"
            . $hit['context'];

        $sources = $hit['sources'] ?? [];
    }

    /**
     * Attach a resolved URL to each KB citation.
     *
     * The widget used to build these links itself from a hardcoded '/help/article' — but the
     * KB addon's public prefix is admin-configurable, so every citation 404'd on any install
     * that changed it. The server knows the real prefix; it should be the one to say.
     *
     * @param  list<array<string,mixed>>  $sources
     * @return list<array<string,mixed>>
     */
    private function citableKbSources(array $sources): array
    {
        $base = KnowledgeBase::articleBaseUrl();

        return array_map(
            fn (array $source): array => $source + [
                'url' => $base . '/' . ($source['slug'] ?? ''),
            ],
            $sources
        );
    }

    /**
     * Resolve the persisted conversation for this request, or null when persistence is
     * unavailable (migration not yet run) — in which case chat still works, statelessly.
     */
    private function resolveConversation(Request $request, array $validated, ?User $user, string $scope): ?AssistantConversation
    {
        $ip = $request->ip();

        return $this->conversations->resolve(
            sessionId: $validated['session_id'],
            scope: $scope,
            user: $user,
            contextPage: $validated['context_page'] ?? null,
            ipHash: $ip ? sha1($ip) : null,
        );
    }

    /**
     * Stream a completion, billing it unconditionally.
     *
     * The previous implementation only charged when the provider volunteered a usage
     * array (`if (isset($chunk['input_tokens']))`), so any provider that omits usage — or
     * any stream that died mid-flight — produced a completely free request. Here usage is
     * accumulated, estimated from character count when the provider reports nothing, and
     * settled in a finally block so an exception or a client disconnect still bills for
     * the tokens that were actually generated.
     */
    private function streamCompletion(
        array $messages,
        string $providerName,
        string $modelName,
        ?User $billingUser,
        string $usageType,
        array $sources = [],
        ?AssistantConversation $conversation = null,
    ): StreamedResponse {
        $temperature = (float) addon_setting('ai-assistant', 'temperature', 0.7);
        $maxTokens = (int) addon_setting('ai-assistant', 'max_tokens', 1024);

        return $this->sseResponse(function () use (
            $messages, $providerName, $modelName, $billingUser, $usageType,
            $sources, $temperature, $maxTokens, $conversation
        ) {
            $this->frame(['type' => 'ready']);

            $inputTokens = 0;
            $outputTokens = 0;
            $reportedUsage = false;
            $output = '';
            $failed = false;

            try {
                // Keys come from the app's central AI key pool (Admin → AI → Keys), not from
                // a key pasted into this addon — one place to rotate, one place to audit.
                $adapter = ProviderRegistry::resolve($providerName);

                foreach ($adapter->streamChatCompletion($messages, $modelName, [
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ]) as $chunk) {
                    if (is_string($chunk)) {
                        $output .= $chunk;
                        $this->frame(['type' => 'token', 'content' => $chunk]);
                    } elseif (is_array($chunk) && isset($chunk['input_tokens'])) {
                        $reportedUsage = true;
                        $inputTokens = (int) $chunk['input_tokens'];
                        $outputTokens = (int) $chunk['output_tokens'];
                        $modelName = $chunk['model'] ?? $modelName;
                    }
                    // Reasoning events are intentionally dropped — the widget shows text only.
                }

                // Citations are emitted AFTER the answer, never before it.
                //
                // They used to be sent immediately after `ready`, so the widget rendered
                // "Sources: …" underneath a message that was still showing the thinking dots
                // — the user was offered references to an answer that did not exist yet, and
                // which (on a provider error) might never arrive. Sending them here means a
                // citation only ever appears attached to a completed answer.
                if ($sources && $output !== '') {
                    $this->frame(['type' => 'sources', 'sources' => $sources]);
                }

                $this->frame(['type' => 'done']);
            } catch (\Throwable $e) {
                $failed = true;

                Log::error('AI Assistant chat error', [
                    'error' => $e->getMessage(),
                    'provider' => $providerName,
                    'model' => $modelName,
                ]);

                // The message is carried in a JSON frame, so it needs no escaping — the old
                // addslashes() mangled apostrophes in user-facing copy, and the bare
                // "ERROR:" sentinel truncated any answer that happened to contain it.
                $this->frame([
                    'type' => 'error',
                    'code' => 'provider',
                    'message' => 'The assistant could not complete that request. Please try again.',
                ]);
            } finally {
                // Estimate whenever the provider gave us nothing usable to bill on — either
                // no usage array at all, or one whose token counts are non-positive (some
                // providers report 0/0 while streaming). Charging an estimate off character
                // count is far closer to correct than letting a real answer go out free,
                // which is exactly what the previous `if (isset($chunk['input_tokens']))`
                // guard allowed. ~4 characters per token is the usual rule of thumb.
                $hasUsableUsage = $reportedUsage && ($inputTokens > 0 || $outputTokens > 0);

                if (! $hasUsableUsage && $output !== '') {
                    $inputTokens = max($inputTokens, $this->estimateTokens(
                        collect($messages)->pluck('content')->implode("\n")
                    ));
                    $outputTokens = max($outputTokens, $this->estimateTokens($output));
                }

                $credits = 0.0;

                if ($failed) {
                    TokenGuard::recordFailure(
                        $billingUser, $providerName, $modelName, $usageType, $inputTokens, $outputTokens
                    );
                } else {
                    $credits = TokenGuard::after(
                        $billingUser, $inputTokens, $outputTokens, $modelName, $providerName, $usageType
                    );
                }

                // Persist whatever the model produced — including a partial answer from a
                // stream that later errored, so the thread reflects what the user actually
                // saw. content_hash on this row is what a later thumbs-up/down joins to.
                $this->conversations->recordAssistantMessage(
                    $conversation, $output, $modelName, $inputTokens, $outputTokens, (float) $credits, $sources
                );
            }
        });
    }

    /**
     * List the slash commands this surface may run. Drives the client's autocomplete menu.
     */
    public function commands(Request $request): JsonResponse
    {
        $isAdmin = $request->routeIs('*admin*');

        if (! $this->assistantService->isVisibleForUser($request->user(), $isAdmin)) {
            return response()->json(['commands' => []]);
        }

        return response()->json([
            'commands' => $this->commands->list(
                $isAdmin ? SlashCommandRegistry::SCOPE_ADMIN : SlashCommandRegistry::SCOPE_USER
            ),
        ]);
    }

    /**
     * The stored transcript for a session, so the widget can restore a thread on open —
     * including across devices for a signed-in user. Scoped by the same visibility gate as
     * chat, and (when signed in) by ownership, so one session id can't surface another
     * user's history.
     */
    public function transcript(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string|max:64',
        ]);

        $isAdmin = $request->routeIs('*admin*');
        $user = $request->user();

        if (! $this->assistantService->isVisibleForUser($user, $isAdmin)) {
            return response()->json(['messages' => []]);
        }

        $scope = $isAdmin ? SlashCommandRegistry::SCOPE_ADMIN : SlashCommandRegistry::SCOPE_USER;

        return response()->json([
            'messages' => $this->conversations->transcript($validated['session_id'], $scope, $user),
        ]);
    }

    // ─── Conversation history ─────────────────────────────────

    /**
     * The signed-in user's past conversations, for the chat history list. Guests get an
     * empty list plus a flag the widget uses to show "Sign in to save your chat history"
     * instead of an empty state.
     */
    public function conversations(Request $request): JsonResponse
    {
        $isAdmin = $request->routeIs('*admin*');
        $user = $request->user();

        if (! $this->assistantService->isVisibleForUser($user, $isAdmin)) {
            return response()->json(['conversations' => [], 'can_save' => false]);
        }

        $scope = $isAdmin ? SlashCommandRegistry::SCOPE_ADMIN : SlashCommandRegistry::SCOPE_USER;

        return response()->json([
            'conversations' => $this->conversations->listForUser($user, $scope),
            // Guests can chat but not keep history — the widget tells them why.
            'can_save' => $user !== null,
        ]);
    }

    public function deleteConversation(Request $request): JsonResponse
    {
        $isAdmin = $request->routeIs('*admin*');
        $user = $request->user();

        if (! $this->assistantService->isVisibleForUser($user, $isAdmin)) {
            return response()->json(['status' => 'error'], 403);
        }

        $validated = $request->validate([
            'session_id' => 'required|string|max:64',
        ]);

        $scope = $isAdmin ? SlashCommandRegistry::SCOPE_ADMIN : SlashCommandRegistry::SCOPE_USER;
        $deleted = $this->conversations->deleteForUser($user, $validated['session_id'], $scope);

        return response()->json(['status' => $deleted ? 'ok' : 'not_found']);
    }

    // ─── Help Center panel ────────────────────────────────────

    /**
     * Published knowledge-base articles for the Help tab's list. Behind the same KB guard
     * as /docs, so the tab is inert (and hidden client-side) when the KB addon is absent.
     */
    public function helpArticles(Request $request): JsonResponse
    {
        if (! $this->helpAvailable($request)) {
            return response()->json(['articles' => [], 'available' => false]);
        }

        $articles = \Addons\PublicKnowledgeBase\Models\KbArticle::query()
            ->published()
            ->orderByDesc('published_at')
            ->paginate(15, ['title', 'slug', 'excerpt', 'published_at']);

        return response()->json([
            'available' => true,
            'articles' => collect($articles->items())->map(fn ($a) => [
                'title' => $a->title,
                'slug' => $a->slug,
                'excerpt' => $a->excerpt,
            ])->all(),
            'has_more' => $articles->hasMorePages(),
            'leave_message' => (bool) addon_setting('ai-assistant', 'enable_message', true),
        ]);
    }

    /**
     * One article plus a few related ones, for the reader view.
     */
    public function helpArticle(Request $request, string $slug): JsonResponse
    {
        if (! $this->helpAvailable($request)) {
            return response()->json(['article' => null], 404);
        }

        $article = \Addons\PublicKnowledgeBase\Models\KbArticle::query()
            ->published()
            ->where('slug', $slug)
            ->first();

        if (! $article) {
            return response()->json(['article' => null], 404);
        }

        $related = [];
        try {
            $related = app(\Addons\PublicKnowledgeBase\Services\KbSearchService::class)
                ->getRelatedArticles($article, 4)
                ->map(fn ($a) => ['title' => $a->title, 'slug' => $a->slug])
                ->all();
        } catch (\Throwable) {
            // related is a nice-to-have; a failure must not break the reader.
        }

        return response()->json([
            'article' => [
                'title' => $article->title,
                'slug' => $article->slug,
                'body' => $article->body, // already sanitised HTML from the KB editor
                'related' => $related,
            ],
        ]);
    }

    /**
     * Keyword search over published articles for the Help tab's search box. A plain LIKE
     * over title/excerpt/plain-text — cheap, and appropriate for "find an article", unlike
     * the semantic embedding search which /docs uses to answer a question.
     */
    public function helpSearch(Request $request): JsonResponse
    {
        if (! $this->helpAvailable($request)) {
            return response()->json(['articles' => []]);
        }

        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json(['articles' => []]);
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';

        $articles = \Addons\PublicKnowledgeBase\Models\KbArticle::query()
            ->published()
            ->where(fn ($q) => $q
                ->where('title', 'like', $like)
                ->orWhere('excerpt', 'like', $like)
                ->orWhere('body_plain', 'like', $like))
            ->orderByDesc('published_at')
            ->limit(15)
            ->get(['title', 'slug', 'excerpt']);

        return response()->json([
            'articles' => $articles->map(fn ($a) => [
                'title' => $a->title,
                'slug' => $a->slug,
                'excerpt' => $a->excerpt,
            ])->all(),
        ]);
    }

    private function helpAvailable(Request $request): bool
    {
        $isAdmin = $request->routeIs('*admin*');

        return $this->assistantService->isVisibleForUser($request->user(), $isAdmin)
            && KnowledgeBase::installed()
            && (bool) addon_setting('ai-assistant', 'enable_help', true);
    }

    // ─── "Leave a message" panel ──────────────────────────────

    /**
     * The email/contact form. Reuses the app's ContactMessage store, so submissions land in
     * the existing admin contact inbox (and its notification/auto-reply flow) rather than a
     * bespoke assistant-only mailbox.
     */
    public function submitMessage(Request $request): JsonResponse
    {
        if (! $this->assistantService->isVisibleForUser($request->user(), $request->routeIs('*admin*'))) {
            return response()->json(['status' => 'error'], 403);
        }

        if (! (bool) addon_setting('ai-assistant', 'enable_message', true)) {
            return response()->json(['status' => 'error', 'message' => 'Messaging is disabled.'], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'message' => 'required|string|max:5000',
            'name' => 'nullable|string|max:255',
            // Honeypot — must stay empty. Bots fill it; humans never see it.
            'website' => 'nullable|string|max:0',
        ]);

        \App\Models\ContactMessage::create([
            'name' => $validated['name'] ?? ($request->user()?->name ?? 'Assistant visitor'),
            'email' => $validated['email'],
            'subject' => 'AI Assistant message',
            'message' => $validated['message'],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'ok',
            'message' => (string) settings('contact_success_message', 'Your message has been sent. We will get back to you soon!'),
        ]);
    }

    // ─── CSAT (chat experience rating) ────────────────────────

    public function csat(Request $request): JsonResponse
    {
        $isAdmin = $request->routeIs('*admin*');

        if (! $this->assistantService->isVisibleForUser($request->user(), $isAdmin)) {
            return response()->json(['status' => 'error'], 403);
        }

        $validated = $request->validate([
            'session_id' => 'required|string|max:64',
            'score' => 'required|integer|min:1|max:5',
            'context_page' => 'nullable|string|max:255',
        ]);

        \Addons\AiAssistant\Models\AssistantCsat::updateOrCreate(
            [
                'session_id' => $validated['session_id'],
                'scope' => $isAdmin ? 'admin' : 'user',
            ],
            [
                'user_id' => $request->user()?->id,
                'score' => $validated['score'],
                'context_page' => $validated['context_page'] ?? null,
            ]
        );

        return response()->json(['status' => 'ok']);
    }

    // ─── SSE plumbing ─────────────────────────────────────────

    /**
     * Typed JSON frames over SSE, replacing the old plaintext protocol whose "READY" and
     * "ERROR:" sentinels were indistinguishable from ordinary model output.
     *
     * Frames: {type:ready} | {type:token,content} | {type:sources,sources[]}
     *       | {type:error,code,message} | {type:done}
     */
    private function sseResponse(callable $body): StreamedResponse
    {
        return new StreamedResponse($body, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    private function frame(array $payload): void
    {
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * A complete, locally-produced answer (slash command or automation rule). Streamed in
     * the same frame format so the client has exactly one code path.
     */
    private function textStream(string $text): StreamedResponse
    {
        return $this->sseResponse(function () use ($text) {
            $this->frame(['type' => 'ready']);
            $this->frame(['type' => 'token', 'content' => $text]);
            $this->frame(['type' => 'done']);
        });
    }

    private function errorStream(string $code, string $message): StreamedResponse
    {
        return $this->sseResponse(function () use ($code, $message) {
            $this->frame(['type' => 'ready']);
            $this->frame(['type' => 'error', 'code' => $code, 'message' => $message]);
        });
    }

    // ─── helpers ──────────────────────────────────────────────

    private function resolveModel(): array
    {
        $provider = addon_setting('ai-assistant', 'provider') ?: settings('default_ai_provider', 'openai');
        $model = addon_setting('ai-assistant', 'model') ?: settings('default_ai_model', 'gpt-4o-mini');

        return [$provider, $model];
    }

    /**
     * A proper role-tagged message array.
     *
     * The frontend previously flattened history into a single "User: …\nAssistant: …"
     * string crammed into one user turn, while admin chat built a real messages[] array.
     * The frontend was getting measurably worse answers than admin for no reason.
     */
    private function buildMessages(string $systemPrompt, array $history, string $message): array
    {
        $messages = [];

        if ($systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        foreach ($history as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }

    private function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Store feedback (thumbs up/down + optional comment).
     */
    public function feedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string|max:64',
            'message_hash' => 'required|string|max:64',
            'rating' => 'required|integer|in:1,-1',
            'comment' => 'nullable|string|max:500',
            'context_page' => 'nullable|string|max:255',
        ]);

        AiAssistantFeedback::updateOrCreate(
            [
                'session_id' => $validated['session_id'],
                'message_hash' => $validated['message_hash'],
            ],
            [
                'message_id' => $this->resolveFeedbackMessageId($validated['session_id'], $validated['message_hash']),
                'user_id' => $request->user()?->id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'context_page' => $validated['context_page'] ?? null,
            ]
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * Find the stored assistant message a thumbs rating refers to, by matching the hash the
     * client sends against the content_hash we recorded. Best-effort: returns null (leaving
     * the rating unlinked but still saved) if persistence is off or no row matches.
     */
    private function resolveFeedbackMessageId(string $sessionId, string $messageHash): ?int
    {
        if (! $this->conversations->available()) {
            return null;
        }

        return \Addons\AiAssistant\Models\AssistantMessage::query()
            ->where('content_hash', $messageHash)
            ->whereHas('conversation', fn ($q) => $q->where('session_id', $sessionId))
            ->value('id');
    }

    /**
     * Admin review of assistant feedback — the read path the table never had. Returns
     * ratings joined to the message they were about (and the user who left them), newest
     * first, with a headline satisfaction split.
     */
    public function feedbackIndex(Request $request): \Inertia\Response
    {
        $feedback = AiAssistantFeedback::query()
            ->with(['message:id,content,model', 'user:id,name,email'])
            ->latest()
            ->paginate(20)
            ->through(fn (AiAssistantFeedback $f) => [
                'id' => $f->id,
                'rating' => $f->rating,
                'comment' => $f->comment,
                'context_page' => $f->context_page,
                'created_at' => $f->created_at?->toDateTimeString(),
                'user' => $f->user ? ['name' => $f->user->name, 'email' => $f->user->email] : null,
                'message' => $f->message ? \Illuminate\Support\Str::limit($f->message->content, 240) : null,
            ]);

        $csat = \Addons\AiAssistant\Models\AssistantCsat::query();

        return \Inertia\Inertia::render('Addons/ai-assistant/Admin/Feedback', [
            'feedback' => $feedback,
            'stats' => [
                'total' => AiAssistantFeedback::count(),
                'positive' => AiAssistantFeedback::where('rating', 1)->count(),
                'negative' => AiAssistantFeedback::where('rating', -1)->count(),
                'csat_count' => (clone $csat)->count(),
                'csat_average' => round((float) (clone $csat)->avg('score'), 2),
            ],
        ]);
    }

    /**
     * Extract text from an uploaded document.
     */
    public function extractText(Request $request): JsonResponse
    {
        // Same access gate as chat. Removing `auth` from the frontend routes (so guests can
        // chat when the admin allows it) would otherwise leave this file-parsing endpoint
        // open to anyone. The admin route is already behind admin.auth.
        $isAdmin = $request->routeIs('*admin*');
        if (! $this->assistantService->isVisibleForUser($request->user(), $isAdmin)) {
            return response()->json(['success' => false, 'error' => 'Not available.'], 403);
        }

        if (! (bool) addon_setting('ai-assistant', 'allow_file_upload', false) && ! $isAdmin) {
            return response()->json(['success' => false, 'error' => 'File upload is disabled.'], 403);
        }

        // The setting is in MB (what an operator actually thinks in); Laravel's `max:` rule
        // for files is in KB, so convert here rather than making the admin do the maths.
        $maxMb = max(1, (int) addon_setting('ai-assistant', 'max_upload_size_mb', 10));

        $request->validate([
            'file' => 'required|file|max:' . ($maxMb * 1024),
        ]);

        $file = $request->file('file');
        if (! $file->isValid()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid file upload.',
            ], 400);
        }

        $tempPath = $file->getRealPath();
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());

        $allowedTypesStr = addon_setting('ai-assistant', 'allowed_file_types', 'pdf,docx,txt,csv,png,jpg');
        $allowedList = array_map(
            fn ($ext) => ltrim(trim($ext), '.'),
            explode(',', strtolower($allowedTypesStr))
        );

        if (! in_array($extension, $allowedList, true)) {
            return response()->json([
                'success' => false,
                'error' => 'File type not allowed: ' . $extension,
            ], 400);
        }

        $tempDir = storage_path('app/temp');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempFile = $tempDir . '/' . uniqid('extract_') . '.' . $extension;
        copy($tempPath, $tempFile);

        $imageExts = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

        try {
            $extractor = app(\App\Services\AI\Rag\TextExtractionService::class);
            if (! $extractor->supports($tempFile)) {
                return response()->json([
                    'success' => false,
                    'error' => "Sorry, {$extension} files aren't supported.",
                ], 400);
            }

            $text = trim($extractor->extract($tempFile));

            // Extraction can succeed yet yield nothing — a scanned image with no text, an
            // empty doc. Say so plainly instead of attaching a blank file the model can't use.
            if ($text === '') {
                return response()->json([
                    'success' => false,
                    'error' => in_array($extension, $imageExts, true)
                        ? "No text could be read from this image."
                        : "This file doesn't seem to contain any readable text.",
                ], 422);
            }

            return response()->json([
                'success' => true,
                'filename' => $originalName,
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            Log::error('AI Assistant text extraction error', [
                'error' => $e->getMessage(),
                'filename' => $originalName,
                'extension' => $extension,
            ]);

            // A specific, actionable message per file family — the previous blanket
            // "Failed to parse that file" told the operator nothing about what to fix.
            // Images route through an LLM vision model, which is the usual failure point:
            // the assistant's configured model may not read images.
            $error = match (true) {
                in_array($extension, $imageExts, true) =>
                    "Couldn't read this image. Reading text from images needs a vision-capable AI model — check the assistant's model setting.",
                in_array($extension, ['pdf'], true) =>
                    "Couldn't read this PDF. It may be scanned (image-only) or password-protected.",
                in_array($extension, ['docx', 'xlsx', 'pptx'], true) =>
                    "Couldn't read this document. It may be corrupted or in an unexpected format.",
                default => "Couldn't read this file. Please try a different one.",
            };

            return response()->json(['success' => false, 'error' => $error], 422);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    // ─── automation rules (admin) ─────────────────────────────

    public function listRules(): JsonResponse
    {
        return response()->json([
            'rules' => \Addons\AiAssistant\Models\AiAssistantRule::orderByDesc('id')->get(),
        ]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $rule = \Addons\AiAssistant\Models\AiAssistantRule::create($this->ruleRules($request));

        return response()->json(['success' => true, 'rule' => $rule]);
    }

    public function updateRule(Request $request, $id): JsonResponse
    {
        $rule = \Addons\AiAssistant\Models\AiAssistantRule::findOrFail($id);
        $rule->update($this->ruleRules($request));

        return response()->json(['success' => true, 'rule' => $rule]);
    }

    public function deleteRule($id): JsonResponse
    {
        \Addons\AiAssistant\Models\AiAssistantRule::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    private function ruleRules(Request $request): array
    {
        return $request->validate([
            'trigger' => 'required|string|max:255',
            'response' => 'required|string',
            'match_type' => 'required|string|in:exact,contains',
            'is_active' => 'required|boolean',
        ]);
    }
}
