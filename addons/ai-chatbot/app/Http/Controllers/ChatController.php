<?php

declare(strict_types=1);

namespace Addons\AiChatbot\Http\Controllers;

use App\Http\Controllers\Controller;
use Addons\AiChatbot\Models\ChatbotMode;
use Addons\AiChatbot\Models\Conversation;
use Addons\AiChatbot\Support\ChatDefaults;
use Addons\AiChatbot\Support\KnowledgeBase;
use App\Services\AI\AiService;
use App\Services\AI\ProviderRegistry;
use App\Services\AI\TokenGuard;
use App\Services\ContentModerationService;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(
        private AiService $ai,
        private ExportService $exportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->getConversationsQuery()->orderByDesc('is_pinned')->latest('last_message_at');

        $historyLimit = (int) $this->getPlanSetting('max_chat_history', 50);
        $limit = ($historyLimit <= 0) ? 1000 : $historyLimit;
        $query->limit($limit);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        } else {
            $query->whereNull('project_id');
        }

        if ($request->filled('tag_id')) {
            $query->whereHas('tags', fn($q) => $q->where('conversation_tags.id', $request->integer('tag_id')));
        }

        return response()->json([
            'success' => true,
            'data' => $query->with('tags')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'mode_slug' => 'nullable|string|exists:chatbot_modes,slug',
            'model' => 'nullable|string|max:150',
            // Scope project ownership to the caller — validating only `exists` lets a
            // user attach their conversation to another user's project (IDOR). A guest
            // ($user === null) has no projects, so this rejects any project_id from them.
            'project_id' => ['nullable', 'integer', Rule::exists('chat_projects', 'id')->where('user_id', $user?->id)],
        ]);

        $conversation = Conversation::create([
            'user_id' => $user?->id,
            'session_id' => $user ? null : session()->getId(),
            'mode_slug' => $validated['mode_slug'] ?? null,
            'project_id' => $validated['project_id'] ?? null,
            'model' => $validated['model'] ?? null,
            'last_message_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $conversation,
        ], 201);
    }

    public function show(Request $request, string $ulid): JsonResponse
    {
        $conversation = $this->getConversationsQuery()->where('ulid', $ulid)->firstOrFail();

        $perPage = 30;
        $beforeId = $request->input('before');

        $query = $conversation->messages()->orderBy('created_at', 'desc');

        if ($beforeId) {
            $beforeMessage = $conversation->messages()->find($beforeId);
            if ($beforeMessage) {
                $query->where('created_at', '<', $beforeMessage->created_at);
            }
        }

        $messages = $query->limit($perPage + 1)->get();
        $hasMore = $messages->count() > $perPage;

        if ($hasMore) {
            $messages = $messages->take($perPage);
        }

        $messages = $messages->sortBy('created_at')->values();

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => $conversation,
                'messages' => $messages,
                'has_more' => $hasMore,
            ],
        ]);
    }

    public function update(Request $request, string $ulid): JsonResponse
    {
        $conversation = $this->getConversationsQuery()->where('ulid', $ulid)->firstOrFail();

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            // Owner-scoped: prevent moving a conversation into someone else's project (IDOR).
            'project_id' => ['nullable', 'integer', Rule::exists('chat_projects', 'id')->where('user_id', Auth::id())],
            'model' => 'nullable|string|max:150',
        ]);

        $conversation->update($validated);

        return response()->json([
            'success' => true,
            'data' => $conversation,
        ]);
    }

    public function destroy(string $ulid): JsonResponse
    {
        $conversation = $this->getConversationsQuery()->where('ulid', $ulid)->firstOrFail();

        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => translate('Conversation deleted.'),
        ]);
    }

    public function togglePin(string $ulid): JsonResponse
    {
        $conversation = $this->getConversationsQuery()->where('ulid', $ulid)->firstOrFail();

        $conversation->update(['is_pinned' => !$conversation->is_pinned]);

        return response()->json([
            'success' => true,
            'data' => $conversation,
        ]);
    }

    public function share(string $ulid): JsonResponse
    {
        $conversation = $this->getConversationsQuery()->where('ulid', $ulid)->firstOrFail();

        if (!$conversation->share_token) {
            $conversation->update(['share_token' => (string) \Illuminate\Support\Str::ulid()]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'share_url' => url("/share/{$conversation->share_token}"),
                'share_token' => $conversation->share_token,
            ],
        ]);
    }

    public function unshare(string $ulid): JsonResponse
    {
        $conversation = $this->getConversationsQuery()->where('ulid', $ulid)->firstOrFail();

        $conversation->update(['share_token' => null]);

        return response()->json(['success' => true]);
    }

    public function sharedView(string $token): JsonResponse
    {
        $conversation = Conversation::where('share_token', $token)->firstOrFail();

        $messages = $conversation->messages()
            ->where('role', 'assistant')
            ->orderBy('created_at')
            ->get(['role', 'content', 'model', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => [
                // The auto-generated title is the first 60 chars of the user's first
                // message. This view deliberately hides user prompts (assistant messages
                // only), so exposing the raw title would leak them — return a neutral label.
                'title' => (string) translate('Shared Conversation'),
                'model' => $conversation->model,
                'messages' => $messages,
            ],
        ]);
    }

    public function branch(Request $request, string $ulid): JsonResponse
    {
        $user = Auth::user();
        $conversation = $this->getConversationsQuery()->where('ulid', $ulid)->firstOrFail();

        $validated = $request->validate([
            'message_id' => 'required|integer|exists:conversation_messages,id',
        ]);

        $branchMessage = $conversation->messages()->find($validated['message_id']);
        if (!$branchMessage) {
            return response()->json(['success' => false, 'message' => 'Message not found.'], 404);
        }

        // Create new conversation branched from this point
        $newConversation = Conversation::create([
            'user_id' => $user?->id,
            'session_id' => $user ? null : session()->getId(),
            'mode_slug' => $conversation->mode_slug,
            'project_id' => $conversation->project_id,
            'model' => $conversation->model,
            'title' => ($conversation->title ?: 'Chat') . ' (branch)',
            'parent_conversation_id' => $conversation->id,
            'branch_point_message_id' => $branchMessage->id,
            'last_message_at' => now(),
        ]);

        // Copy messages up to and including the branch point
        $messagesUpToBranch = $conversation->messages()
            ->where('created_at', '<=', $branchMessage->created_at)
            ->orderBy('created_at')
            ->get();

        foreach ($messagesUpToBranch as $msg) {
            $newConversation->messages()->create([
                'role' => $msg->role,
                'content' => $msg->content,
                'model' => $msg->model,
                'input_tokens' => $msg->input_tokens,
                'output_tokens' => $msg->output_tokens,
                'credits_charged' => $msg->credits_charged,
                'attachments' => $msg->attachments,
                'created_at' => $msg->created_at,
            ]);
        }

        $newConversation->update([
            'message_count' => $messagesUpToBranch->count(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $newConversation,
        ], 201);
    }

    public function editMessage(Request $request, string $ulid, int $messageId): JsonResponse
    {
        $user = Auth::user();
        // Editing is signed-in-only; a guest reaching this (via ChatGuestAccess) would
        // otherwise dereference null. Respond 403 instead of 500.
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Sign in to edit messages.'], 403);
        }

        $conversation = Conversation::where('ulid', $ulid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'content' => 'required|string|max:16000',
        ]);

        $message = $conversation->messages()->where('role', 'user')->find($messageId);
        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found or cannot be edited.',
            ], 404);
        }

        // Update the message content
        $message->update(['content' => $validated['content']]);

        // Delete all messages after this one (both user and assistant)
        $conversation->messages()
            ->where('created_at', '>', $message->created_at)
            ->delete();

        // Recalculate message count
        $messageCount = $conversation->messages()->count();
        $conversation->update(['message_count' => $messageCount]);

        return response()->json([
            'success' => true,
            'message' => translate('Message updated successfully.'),
            'data' => [
                'message' => $message,
                'should_regenerate' => true,
            ],
        ]);
    }

    public function export(Request $request, string $ulid): \Symfony\Component\HttpFoundation\Response
    {
        $conversation = $this->getConversationsQuery()->where('ulid', $ulid)->firstOrFail();

        $format = $request->input('format', 'md');
        $messages = $conversation->messages()->orderBy('created_at')->get();

        $title = $conversation->title ?: 'Chat Export';
        $filename = \Illuminate\Support\Str::slug($title) . '-' . $conversation->ulid;

        if ($format === 'json') {
            $data = [
                'conversation' => [
                    'ulid' => $conversation->ulid,
                    'title' => $conversation->title,
                    'model' => $conversation->model,
                    'created_at' => $conversation->created_at->toIso8601String(),
                    'message_count' => $conversation->message_count,
                ],
                'messages' => $messages->map(fn ($m) => [
                    'role' => $m->role,
                    'content' => $m->content,
                    'model' => $m->model,
                    'created_at' => $m->created_at?->toIso8601String(),
                    'attachments' => $m->attachments,
                ]),
            ];

            return response()->json($data, 200, [
                'Content-Disposition' => "attachment; filename=\"{$filename}.json\"",
            ]);
        }

        if ($format === 'pdf') {
            // Generate markdown first, then convert to HTML for PDF
            $markdown = $this->generateMarkdownExport($conversation, $messages);
            $html = $this->markdownToHtml($markdown);

            return $this->exportService->downloadPdfFromHtml($html, $filename);
        }

        // Default: markdown
        $markdown = $this->generateMarkdownExport($conversation, $messages);
        return response($markdown, 200, [
            'Content-Type' => 'text/markdown',
            'Content-Disposition' => "attachment; filename=\"{$filename}.md\"",
        ]);
    }

    private function generateMarkdownExport(Conversation $conversation, $messages): string
    {
        $title = $conversation->title ?: 'Chat Export';
        $date = $conversation->created_at->format('Y-m-d H:i:s');

        $md = "# {$title}\n\n";
        $md .= "**Exported:** {$date}  \n";
        $md .= "**Model:** {$conversation->model}  \n";
        $md .= "**Messages:** {$conversation->message_count}\n\n";
        $md .= "---\n\n";

        foreach ($messages as $msg) {
            $role = ucfirst($msg->role);
            $time = $msg->created_at?->format('H:i:s') ?? '';

            $md .= "## {$role}";
            if ($time) {
                $md .= " _({$time})_";
            }
            $md .= "\n\n";
            $md .= $msg->content . "\n\n";

            if ($msg->attachments) {
                $md .= "**Attachments:**\n";
                foreach ($msg->attachments as $att) {
                    $md .= "- {$att['name']} ({$att['type']})\n";
                }
                $md .= "\n";
            }

            $md .= "---\n\n";
        }

        return $md;
    }

    private function markdownToHtml(string $markdown): string
    {
        $html = \Illuminate\Support\Str::markdown($markdown);

        return '<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "DejaVu Sans", sans-serif; max-width: 800px; margin: 0 auto; padding: 40px; line-height: 1.6; color: #333; }
        h1 { border-bottom: 2px solid #eee; padding-bottom: 10px; }
        h2 { margin-top: 30px; color: #555; }
        hr { border: none; border-top: 1px solid #eee; margin: 20px 0; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
        em { color: #888; }
    </style>
</head>
<body>' . $html . '</body></html>';
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'chat_custom_instructions' => 'nullable|string|max:2000',
            'custom_instructions' => 'nullable|string|max:2000',
        ]);

        $instructions = $request->input('chat_custom_instructions') ?? $request->input('custom_instructions');

        if ($user) {
            $user->update([
                'chat_custom_instructions' => $instructions,
            ]);
        } else {
            session(['chat_custom_instructions' => $instructions]);
        }

        return response()->json([
            'success' => true,
            'message' => translate('Settings updated successfully.'),
            'data' => [
                'chat_custom_instructions' => $user ? $user->chat_custom_instructions : session('chat_custom_instructions'),
            ],
        ]);
    }

    public function sendMessage(Request $request, string $ulid): StreamedResponse
    {
        $user = Auth::user();
        $conversation = $this->getConversationsQuery()->where('ulid', $ulid)->firstOrFail();

        // 0. Enforce guest max messages limit if not authenticated
        if (!$user) {
            $maxGuestMessages = (int) addon_setting('ai-chatbot', 'guest_max_messages', 10);
            $count = \Addons\AiChatbot\Models\ConversationMessage::where('role', 'user')
                ->whereHas('conversation', fn($q) => $q->whereNull('user_id')->where('session_id', session()->getId()))
                ->count();
            if ($count >= $maxGuestMessages) {
                return response()->stream(function () use ($maxGuestMessages) {
                    echo 'data: '.json_encode(['type' => 'error', 'message' => "You have reached the guest limit of {$maxGuestMessages} messages. Please sign in to continue."])."\n\n";
                    echo 'data: '.json_encode(['type' => 'done'])."\n\n";
                }, 200, [
                    'Content-Type' => 'text/event-stream',
                    'X-Accel-Buffering' => 'no',
                    'Cache-Control' => 'no-cache',
                ]);
            }
        }

        // 1. Enforce 5-hour limit
        $limit5h = (int) $this->getPlanSetting('max_messages_5h', 0);
        if ($limit5h > 0) {
            $count = \Addons\AiChatbot\Models\ConversationMessage::where('role', 'user')
                ->whereHas('conversation', function($q) use ($user) {
                    if ($user) {
                        $q->where('user_id', $user->id);
                    } else {
                        $q->whereNull('user_id')->where('session_id', session()->getId());
                    }
                })
                ->where('created_at', '>=', now()->subHours(5))
                ->count();
            if ($count >= $limit5h) {
                return response()->stream(function () use ($limit5h) {
                    echo 'data: '.json_encode(['type' => 'error', 'message' => "You have reached your limit of {$limit5h} messages per 5 hours."])."\n\n";
                    echo 'data: '.json_encode(['type' => 'done'])."\n\n";
                }, 200, [
                    'Content-Type' => 'text/event-stream',
                    'X-Accel-Buffering' => 'no',
                    'Cache-Control' => 'no-cache',
                ]);
            }
        }

        // 2. Enforce weekly limit
        $limitWeekly = (int) $this->getPlanSetting('max_messages_weekly', 0);
        if ($limitWeekly > 0) {
            $count = \Addons\AiChatbot\Models\ConversationMessage::where('role', 'user')
                ->whereHas('conversation', function($q) use ($user) {
                    if ($user) {
                        $q->where('user_id', $user->id);
                    } else {
                        $q->whereNull('user_id')->where('session_id', session()->getId());
                    }
                })
                ->where('created_at', '>=', now()->subDays(7))
                ->count();
            if ($count >= $limitWeekly) {
                return response()->stream(function () use ($limitWeekly) {
                    echo 'data: '.json_encode(['type' => 'error', 'message' => "You have reached your limit of {$limitWeekly} messages per week."])."\n\n";
                    echo 'data: '.json_encode(['type' => 'done'])."\n\n";
                }, 200, [
                    'Content-Type' => 'text/event-stream',
                    'X-Accel-Buffering' => 'no',
                    'Cache-Control' => 'no-cache',
                ]);
            }
        }

        // 3. Enforce monthly limit
        $limitMonthly = (int) $this->getPlanSetting('max_messages_monthly', 0);
        if ($limitMonthly > 0) {
            $count = \Addons\AiChatbot\Models\ConversationMessage::where('role', 'user')
                ->whereHas('conversation', function($q) use ($user) {
                    if ($user) {
                        $q->where('user_id', $user->id);
                    } else {
                        $q->whereNull('user_id')->where('session_id', session()->getId());
                    }
                })
                ->where('created_at', '>=', now()->subDays(30))
                ->count();
            if ($count >= $limitMonthly) {
                return response()->stream(function () use ($limitMonthly) {
                    echo 'data: '.json_encode(['type' => 'error', 'message' => "You have reached your limit of {$limitMonthly} messages per month."])."\n\n";
                    echo 'data: '.json_encode(['type' => 'done'])."\n\n";
                }, 200, [
                    'Content-Type' => 'text/event-stream',
                    'X-Accel-Buffering' => 'no',
                    'Cache-Control' => 'no-cache',
                ]);
            }
        }

        // 4. Enforce custom credits per message balance check
        $creditsPerMessage = $this->getPlanSetting('credits_per_message', null);
        if ($user && $creditsPerMessage !== null && $creditsPerMessage !== '') {
            $requiredCredits = (float) $creditsPerMessage;
            if (! credit_quota_mode() && $user->credits < $requiredCredits) {
                return response()->stream(function () use ($requiredCredits, $user) {
                    echo 'data: '.json_encode(['type' => 'error', 'message' => "Insufficient credits. You need at least {$requiredCredits} credits to send a message, but you only have " . round((float)$user->credits, 2) . " credits."])."\n\n";
                    echo 'data: '.json_encode(['type' => 'done'])."\n\n";
                }, 200, [
                    'Content-Type' => 'text/event-stream',
                    'X-Accel-Buffering' => 'no',
                    'Cache-Control' => 'no-cache',
                ]);
            }
        }

        $validated = $request->validate([
            'content' => 'required|string|max:16000',
            'mode_slug' => 'nullable|string|exists:chatbot_modes,slug',
            'model' => 'nullable|string|max:150',
            'attachments' => 'nullable|array|max:5',
            'attachments.*.id' => 'required|string|max:26',
            'attachments.*.name' => 'required|string|max:255',
            'attachments.*.type' => 'required|string|max:100',
            'attachments.*.size' => 'required|integer|min:1',
            'attachments.*.extension' => 'required|string|max:10',
            'attachments.*.storage_path' => 'required|string|max:500',
            'attachments.*.text_content' => 'nullable|string|max:20000',
            'use_knowledge_base' => 'nullable|boolean',
        ]);

        // Content safety gate — reject unsafe prompts before any credits are
        // charged or the AI provider is called. No-op unless the Content
        // Moderation extension is enabled in `block` mode.
        if (ContentModerationService::fromSettings()->textViolates($validated['content'], 'chatbot')) {
            return response()->stream(function () {
                echo 'data: '.json_encode(['type' => 'error', 'message' => translate('This message was blocked by content safety filters.')])."\n\n";
                echo 'data: '.json_encode(['type' => 'done'])."\n\n";
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'X-Accel-Buffering' => 'no',
                'Cache-Control' => 'no-cache',
            ]);
        }

        // Security: `storage_path` is client-supplied. Ensure every attachment
        // points inside THIS caller's own upload directory — otherwise a crafted
        // message could reference another user's file (or traverse the disk) and
        // have preview() serve it back. See ChatAttachmentController::preview().
        if (! empty($validated['attachments'])) {
            $ownerDir = 'chat-attachments/' . ($user ? (string) $user->id : 'guest_' . session()->getId()) . '/';
            foreach ($validated['attachments'] as $attachment) {
                $storagePath = $attachment['storage_path'] ?? '';
                if (! str_starts_with($storagePath, $ownerDir) || str_contains($storagePath, '..')) {
                    abort(403, 'Invalid attachment reference.');
                }
            }
        }

        $mode = null;
        if ($validated['mode_slug'] ?? null) {
            $mode = ChatbotMode::where('slug', $validated['mode_slug'])->active()->first();
        } elseif ($conversation->mode_slug) {
            $mode = ChatbotMode::where('slug', $conversation->mode_slug)->active()->first();
        }

        // Resolve default model for the active mode
        $modeDefaultModel = null;
        if ($mode) {
            $modeDefaultModels = addon_setting('ai-chatbot', 'mode_default_models', []);
            if (is_array($modeDefaultModels)) {
                $modeDefaultModel = $modeDefaultModels[$mode->slug] ?? null;
            }
            if (empty($modeDefaultModel)) {
                $modeDefaultModel = $mode->default_model;
            }
        }

        $globalDefaultModel = ChatDefaults::chatModel();
        $model = $validated['model'] ?? $conversation->model ?? $modeDefaultModel ?? $globalDefaultModel;

        $systemPrompt = $mode?->system_prompt ?? null;

        // Handle custom models if enabled
        if ((bool) addon_setting('ai-chatbot', 'show_custom_models', false)) {
            $customModels = addon_setting('ai-chatbot', 'custom_models', []);
            if (is_array($customModels)) {
                foreach ($customModels as $customModel) {
                    if (isset($customModel['id']) && $customModel['id'] === $model) {
                        if (!empty($customModel['system_prompt'])) {
                            $systemPrompt = (!empty($systemPrompt) ? $systemPrompt . "\n\n" : '') . $customModel['system_prompt'];
                        }
                        $model = $customModel['model'] ?? $globalDefaultModel;
                        break;
                    }
                }
            }
        }

        // Validate model exists and is active
        $aiModel = \App\Models\AiModel::where('slug', $model)->where('is_active', true)->first();
        if (!$aiModel) {
            return response()->stream(function () use ($model) {
                echo 'data: '.json_encode(['type' => 'error', 'message' => "Model '{$model}' is not available."])."\n\n";
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'X-Accel-Buffering' => 'no',
                'Cache-Control' => 'no-cache',
            ]);
        }

        // Inject user's custom instructions if they exist
        $instructions = $user ? $user->chat_custom_instructions : session('chat_custom_instructions');
        if ($instructions) {
            $customInstructions = "\n\nUser's Custom Instructions:\n" . $instructions;
            $systemPrompt = ($systemPrompt ?? '') . $customInstructions;
        }

        // Inject knowledge base context if requested. KnowledgeBase::available()
        // also covers the case where the KB addon ships with the build but is not
        // installed/activated — its tables would not exist.
        $kbSources = [];
        if (!empty($validated['use_knowledge_base']) && KnowledgeBase::available()) {
            try {
                $kbService = app(\Addons\AiKnowledgeBase\Services\KbSearchService::class);
                $kbResult = $kbService->getRelevantContext($validated['content']);

                if (!empty($kbResult['context'])) {
                    $kbContext = "\n\nKnowledge Base Context (use this information to help answer the user's question):\n" . $kbResult['context'];
                    $systemPrompt = ($systemPrompt ?? '') . $kbContext;
                    $kbSources = $kbResult['sources'];
                }
            } catch (\Throwable $e) {
                // Silently fail - KB is optional
            }
        }

        $modeSwitch = null;
        if ($mode && $conversation->mode_slug !== $mode->slug) {
            $modeSwitch = $mode->slug;
            $conversation->update(['mode_slug' => $mode->slug]);
        }

        $attachments = $validated['attachments'] ?? null;

        $userMsg = $conversation->messages()->create([
            'role' => 'user',
            'content' => $validated['content'],
            'model' => $model,
            'mode_switch' => $modeSwitch,
            'attachments' => $attachments,
        ]);

        if (! $conversation->title) {
            $conversation->update([
                'title' => mb_substr($validated['content'], 0, 60),
            ]);
        }

        $history = $conversation->messages()
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->reverse();

        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        foreach ($history as $msg) {
            $content = $msg->content;

            // Handle user messages with attachments
            if ($msg->role === 'user' && $msg->attachments) {
                $imageAttachments = [];
                $textAttachments = [];

                foreach ($msg->attachments as $attachment) {
                    $mimeType = $attachment['type'] ?? '';

                    // Check if it's an image
                    if (str_starts_with($mimeType, 'image/')) {
                        $imageAttachments[] = $attachment;
                    } elseif (!empty($attachment['text_content'])) {
                        $textAttachments[] = "[File: {$attachment['name']}]\n{$attachment['text_content']}";
                    }
                }

                // If there are images, use multi-modal format
                if (!empty($imageAttachments)) {
                    $contentParts = [['type' => 'text', 'text' => $content]];

                    // Add image URLs as base64 data
                    foreach ($imageAttachments as $img) {
                        $imgPath = $img['storage_path'] ?? null;
                        if ($imgPath && Storage::disk('local')->exists($imgPath)) {
                            $fullPath = Storage::disk('local')->path($imgPath);
                            $imgData = file_get_contents($fullPath);
                            $base64 = base64_encode($imgData);
                            $dataUrl = "data:{$mimeType};base64,{$base64}";
                            $contentParts[] = [
                                'type' => 'image_url',
                                'image_url' => ['url' => $dataUrl],
                            ];
                        }
                    }

                    // Append text attachment content
                    if (!empty($textAttachments)) {
                        $contentParts[0]['text'] .= "\n\n" . implode("\n\n", $textAttachments);
                    }

                    $messages[] = ['role' => $msg->role, 'content' => $contentParts];
                } else {
                    // No images, use traditional text format
                    if (!empty($textAttachments)) {
                        $content .= "\n\n" . implode("\n\n", $textAttachments);
                    }
                    $messages[] = ['role' => $msg->role, 'content' => $content];
                }
            } else {
                $messages[] = ['role' => $msg->role, 'content' => $content];
            }
        }

        $provider = $model ? $this->resolveProvider($model) : (string) settings('default_ai_provider', 'openai');

        try {
            TokenGuard::before($user, null, $model);
        } catch (\Throwable $e) {
            return response()->stream(function () use ($e) {
                echo 'data: '.json_encode(['type' => 'error', 'message' => $e->getMessage()])."\n\n";
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'X-Accel-Buffering' => 'no',
                'Cache-Control' => 'no-cache',
            ]);
        }

        // Reserve the flat per-message credits BEFORE streaming. The old code charged
        // after generation, so N concurrent requests each passed the pre-flight balance
        // check (none had debited yet), got N full generations, and only ~1 actually
        // charged — the rest silently failed to deduct but kept the output. chargeCredits()
        // locks the row and refuses to go negative, so reserving up front serializes the
        // concurrent requests on the wallet. Refunded inside the stream on cancel/failure.
        $reservedCredits = 0.0;
        $creditsPerMessage = $this->getPlanSetting('credits_per_message', null);
        if ($user && $creditsPerMessage !== null && $creditsPerMessage !== '') {
            $reservedCredits = (float) $creditsPerMessage;
            if ($reservedCredits > 0 && ! $user->chargeCredits($reservedCredits, "AI Chatbot message: {$model}", [
                'provider' => $provider,
                'model' => $model,
            ])) {
                return response()->stream(function () use ($reservedCredits, $user) {
                    echo 'data: '.json_encode(['type' => 'error', 'message' => "Insufficient credits. You need at least {$reservedCredits} credits to send a message, but you only have " . round((float) $user->credits, 2) . " credits."])."\n\n";
                    echo 'data: '.json_encode(['type' => 'done'])."\n\n";
                }, 200, [
                    'Content-Type' => 'text/event-stream',
                    'X-Accel-Buffering' => 'no',
                    'Cache-Control' => 'no-cache',
                ]);
            }
        }

        return response()->stream(function () use (
            $conversation, $user, $model, $provider, $messages, $kbSources, $reservedCredits

        ) {
            $fullContent = '';

            // The flat per-message credits were reserved (charged) up front and must be
            // settled EXACTLY ONCE — either kept (successful generation) or refunded
            // (cancel / provider error / any failure). $settled guards every path so a
            // refund never runs twice (e.g. error path + finally safety-net). The refund
            // reuses the mode-aware refundCredits() — never a raw increment('credits') —
            // so it is correct in both quota and metered billing modes.
            $settled = false;
            $refundReserved = function () use (&$settled, $user, $reservedCredits, $provider, $model) {
                if ($settled) {
                    return;
                }
                $settled = true;

                if ($user && $reservedCredits > 0) {
                    $user->refundCredits($reservedCredits, "Refund — cancelled AI Chatbot message: {$model}", [
                        'provider' => $provider,
                        'model' => $model,
                    ]);
                }
            };

            try {
                $adapter = ProviderRegistry::resolve($provider);

                $maxTokens = (int) $this->getPlanSetting('max_tokens', 4096);
                if ($maxTokens <= 0) {
                    $maxTokens = 4096;
                }

                $stream = $adapter->streamChatCompletion(
                    $messages,
                    $model,
                    ['temperature' => 0.7, 'max_tokens' => $maxTokens]
                );

                $startTime = microtime(true);

                foreach ($stream as $chunk) {
                    if (is_string($chunk)) {
                        $token = $chunk;
                        $fullContent .= $token;
                        echo 'data: '.json_encode(['type' => 'token', 'content' => $token])."\n\n";
                    } elseif (is_array($chunk)) {
                        if (isset($chunk['reasoning_start'])) {
                            echo 'data: '.json_encode(['type' => 'reasoning_start'])."\n\n";
                        } elseif (isset($chunk['reasoning_end'])) {
                            echo 'data: '.json_encode(['type' => 'reasoning_end'])."\n\n";
                        } elseif (isset($chunk['reasoning'])) {
                            echo 'data: '.json_encode(['type' => 'reasoning', 'content' => $chunk['reasoning']])."\n\n";
                        }
                    }

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();

                    if (connection_aborted()) {
                        break;
                    }
                }

                // Knowledge Base citations are emitted AFTER the answer, never before it.
                //
                // They used to be sent before the first token, so the chat rendered
                // "Sources: …" under a message that was still showing the typing indicator —
                // references to an answer that did not exist yet, and which (on a provider
                // error) might never arrive at all. Emitting them here ties a citation to a
                // completed answer.
                if (! empty($kbSources) && $fullContent !== '') {
                    echo 'data: '.json_encode(['type' => 'kb_sources', 'sources' => $kbSources])."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                $responseTime = (int) ((microtime(true) - $startTime) * 1000);
                $success = ! connection_aborted();

                $assistantMsg = $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $fullContent,
                    'model' => $model,
                ]);

                $inputTokens = $this->estimateTokens($messages);
                $outputTokens = $this->estimateTokens([['role' => 'assistant', 'content' => $fullContent]]);

                $creditsPerMessage = $this->getPlanSetting('credits_per_message', null);
                if ($user && $creditsPerMessage !== null && $creditsPerMessage !== '') {
                    // Flat credits were reserved (charged) up front. Refund if the
                    // generation was cancelled/failed so the user isn't billed for nothing;
                    // otherwise mark the reservation as settled (kept) so the finally
                    // safety-net below does not refund a successfully billed message.
                    $credits = $reservedCredits;
                    if (! $success) {
                        $refundReserved();
                        $credits = 0.0;
                    } else {
                        $settled = true;
                    }

                    TokenGuard::after(
                        $user,
                        $inputTokens,
                        $outputTokens,
                        $model,
                        $provider,
                        'chat',
                        ['conversation_ulid' => $conversation->ulid],
                        false, // don't charge since we reserved up front
                        $success,
                        $responseTime
                    );

                    if ($success) {
                        \App\Models\AiUsageLog::where('user_id', $user->id)
                            ->where('type', 'chat')
                            ->latest()
                            ->first()
                            ?->update(['credits_used' => $credits]);
                    }
                } else {
                    $credits = TokenGuard::after(
                        $user,
                        $inputTokens,
                        $outputTokens,
                        $model,
                        $provider,
                        'chat',
                        ['conversation_ulid' => $conversation->ulid],
                        $user ? true : false, // don't charge if guest, but log
                        $success,
                        $responseTime
                    );
                }

                // Persist the usage on the assistant message row. Without this the
                // per-message usage line (ChatMessage.vue) is blank after a page reload
                // and any per-message reporting reads zeros — only the live SSE `usage`
                // event carried these numbers before.
                $assistantMsg->update([
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'credits_charged' => $credits,
                ]);

                $conversation->increment('message_count', 2);
                $conversation->increment('total_tokens', $inputTokens + $outputTokens);
                $conversation->increment('total_credits', $credits);
                $conversation->update(['last_message_at' => now(), 'model' => $model]);

                $status = $success ? 'success' : 'cancelled';

                echo 'data: '.json_encode([
                    'type' => 'usage',
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'credits' => $credits,
                    'model' => $model,
                    'status' => $status,
                ])."\n\n";

            } catch (\Throwable $e) {
                // Provider errored mid-stream. Refund the up-front reservation FIRST so a
                // failed message is never billed — even if persisting the partial answer
                // below throws. Mode-aware and idempotent via $refundReserved().
                $refundReserved();

                echo 'data: '.json_encode([
                    'type' => 'error',
                    'message' => $this->sanitizeError($e->getMessage()),
                ])."\n\n";

                if (! empty($fullContent)) {
                    $conversation->messages()->create([
                        'role' => 'assistant',
                        'content' => $fullContent,
                        'model' => $model,
                    ]);
                }
            } finally {
                // Safety net for any path that neither settled on success nor hit the
                // catch (e.g. an unexpected termination): settle the reservation exactly
                // once. No-op when already kept or refunded.
                $refundReserved();
            }

            echo 'data: '.json_encode(['type' => 'done'])."\n\n";

            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Map a model slug to the provider that actually serves it.
     *
     * The ai_models catalog is authoritative and must be consulted first. Aggregators
     * re-sell other vendors' models under their own account, so the vendor name inside
     * the slug says nothing about who to bill: on an OpenRouter-only install every model
     * is served by OpenRouter, yet 'deepseek/deepseek-v4-flash' used to be matched on the
     * substring 'deepseek' and sent to api.deepseek.com — which has no key configured and
     * answers 401. sanitizeError() then reported that as "This AI provider is not
     * configured", pointing the admin at the one provider they had set up correctly.
     *
     * Mirrors App\Services\AI\PromptBuilder::resolveProvider().
     */
    private function resolveProvider(string $model): string
    {
        $catalogued = \App\Models\AiModel::where('slug', $model)->value('provider');
        if ($catalogued) {
            return (string) $catalogued;
        }

        // Not in the catalog — infer from the slug prefix. Only a bare prefix is a real
        // signal; a vendor name appearing mid-slug usually means an aggregator route.
        $slug = strtolower($model);

        return match (true) {
            str_starts_with($slug, 'gpt-') => 'openai',
            str_starts_with($slug, 'o1'), str_starts_with($slug, 'o3'), str_starts_with($slug, 'o4') => 'openai',
            str_starts_with($slug, 'dall-e') => 'openai',
            str_starts_with($slug, 'claude') => 'anthropic',
            str_starts_with($slug, 'gemini') => 'google',
            str_starts_with($slug, 'deepseek') => 'deepseek',
            str_starts_with($slug, 'grok') => 'xai',
            str_starts_with($slug, 'mistral') => 'mistral',
            str_starts_with($slug, 'perplexity'), str_starts_with($slug, 'sonar') => 'perplexity',
            default => (string) settings('default_ai_provider', 'openai'),
        };
    }

    private function estimateTokens(array $messages): int
    {
        $text = '';
        foreach ($messages as $msg) {
            $text .= ($msg['content'] ?? '').' ';
        }

        // str_word_count counts ~0 for CJK/Arabic/emoji-heavy text, which systematically
        // undercharges non-Latin usage. Take the max of the word-based estimate and a
        // char-based floor (~4 chars/token) so scripts without spaces are still counted.
        return max(1, (int) max(str_word_count($text, 0) * 1.3, mb_strlen($text) / 4));
    }

    private function sanitizeError(string $message): string
    {
        $lower = mb_strtolower($message);

        if (preg_match('/rate.?limit|too many requests|quota exceeded|insufficient_?quota|billing.*quota|credits? exhausted|429/i', $lower)) {
            return (string) translate('Rate limit reached. Please try again in a moment.');
        }
        if (preg_match('/content.?policy|content.?filter|safety filter|flagged by|inappropriate|violates.*policy/i', $lower)) {
            return (string) translate('Your request was flagged. Please modify your input and try again.');
        }
        if (preg_match('/context.?length|token.?limit|max.?tokens|too long|exceed.*token|exceed.*context/i', $lower)) {
            return (string) translate('Your input is too long. Please shorten it and try again.');
        }
        if (preg_match('/timeout|timed.?out/i', $lower)) {
            return (string) translate('Generation timed out. Try a shorter length or a different model.');
        }
        if (preg_match('/api.?key|api.?key|invalid.?key|authentication|unauthorized|401|not.?authorized/i', $lower)) {
            return (string) translate('This AI provider is not configured. Please contact support.');
        }
        if (preg_match('/model.?not.?found|model.?unavailable|invalid.?model|unsupported.?model|no.?such.?model|deprecated/i', $lower)) {
            return (string) translate('The selected model is unavailable. Please try a different one.');
        }
        if (preg_match('/network.?error|connection.?refused|connection.?reset|econnrefused|econnreset|enotfound|etimedout/i', $lower)) {
            return (string) translate('Connection error. Please check your internet and try again.');
        }
        if (preg_match('/internal.?server.?error|bad.?gateway|gateway.?timeout|service.?unavailable|overloaded|500|502|503|504/i', $lower)) {
            return (string) translate('The AI service is temporarily unavailable. Please try again later.');
        }
        if (preg_match('/stream.?error|sse.?error/i', $lower)) {
            return (string) translate('Generation interrupted. Please try again.');
        }
        if (preg_match('/exception|stack.?trace|\.php/i', $lower)) {
            return (string) translate('Something went wrong. Please try again or contact support.');
        }

        // Default: never pass an unmatched raw provider/internal message to the client
        // (it can leak endpoints, config hints, or stack details). Log it server-side.
        \Log::warning('AI Chatbot generation error', ['message' => $message]);

        return (string) translate('Something went wrong. Please try again or contact support.');
    }

    private function getPlanSetting(string $key, $default = null)
    {
        $user = Auth::user();

        if ($user) {
            $plan = $user->plan;
            if ($plan && !$plan->is_free) {
                $planKey = "plan_{$plan->slug}_{$key}";
                $val = addon_setting('ai-chatbot', $planKey);
                if ($val !== null && $val !== '') {
                    if ($val === 'true' || $val === '1') return true;
                    if ($val === 'false' || $val === '0') return false;
                    return $val;
                }
            } else {
                $freeKey = "free_{$key}";
                $val = addon_setting('ai-chatbot', $freeKey);
                if ($val !== null && $val !== '') {
                    if ($val === 'true' || $val === '1') return true;
                    if ($val === 'false' || $val === '0') return false;
                    return $val;
                }
            }
        } else {
            $guestKey = "guest_{$key}";
            $val = addon_setting('ai-chatbot', $guestKey);
            if ($val !== null && $val !== '') {
                if ($val === 'true' || $val === '1') return true;
                if ($val === 'false' || $val === '0') return false;
                return $val;
            }
        }

        return $default;
    }

    private function getConversationsQuery()
    {
        $user = Auth::user();
        if ($user) {
            return $user->conversations();
        }

        return \Addons\AiChatbot\Models\Conversation::whereNull('user_id')
            ->where('session_id', session()->getId());
    }
}
