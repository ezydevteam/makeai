<?php

declare(strict_types=1);

namespace Addons\AiChatbot\Http\Controllers;

use App\Http\Controllers\Controller;
use Addons\AiChatbot\Models\ChatbotProduct;
use Addons\AiChatbot\Models\Conversation;
use App\Services\AI\AiService;
use App\Services\AI\ProviderRegistry;
use App\Services\AI\TokenGuard;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(
        private AiService $ai,
        private ExportService $exportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = $user->conversations()->orderByDesc('is_pinned')->latest('last_message_at');

        $unlimited = $this->getPlanSetting('unlimited_history', false);
        $limit = $unlimited ? 1000 : (int) $this->getPlanSetting('max_chat_history', 50);
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
            'product_slug' => 'nullable|string|exists:chatbot_products,slug',
            'model' => 'nullable|string|max:150',
            'project_id' => 'nullable|integer|exists:chat_projects,id',
        ]);

        $conversation = $user->conversations()->create([
            'product_slug' => $validated['product_slug'] ?? null,
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
        $conversation = Conversation::where('ulid', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

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
        $conversation = Conversation::where('ulid', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'project_id' => 'nullable|integer|exists:chat_projects,id',
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
        $conversation = Conversation::where('ulid', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => translate('Conversation deleted.'),
        ]);
    }

    public function togglePin(string $ulid): JsonResponse
    {
        $conversation = Conversation::where('ulid', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $conversation->update(['is_pinned' => !$conversation->is_pinned]);

        return response()->json([
            'success' => true,
            'data' => $conversation,
        ]);
    }

    public function share(string $ulid): JsonResponse
    {
        $conversation = Conversation::where('ulid', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

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
        $conversation = Conversation::where('ulid', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

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
                'title' => $conversation->title,
                'model' => $conversation->model,
                'messages' => $messages,
            ],
        ]);
    }

    public function branch(Request $request, string $ulid): JsonResponse
    {
        $user = Auth::user();
        $conversation = Conversation::where('ulid', $ulid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'message_id' => 'required|integer|exists:conversation_messages,id',
        ]);

        $branchMessage = $conversation->messages()->find($validated['message_id']);
        if (!$branchMessage) {
            return response()->json(['success' => false, 'message' => 'Message not found.'], 404);
        }

        // Create new conversation branched from this point
        $newConversation = $user->conversations()->create([
            'product_slug' => $conversation->product_slug,
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
        $user = Auth::user();
        $conversation = Conversation::where('ulid', $ulid)
            ->where('user_id', $user->id)
            ->firstOrFail();

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
        ]);

        $user->update([
            'chat_custom_instructions' => $validated['chat_custom_instructions'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => translate('Settings updated successfully.'),
            'data' => [
                'chat_custom_instructions' => $user->chat_custom_instructions,
            ],
        ]);
    }

    public function sendMessage(Request $request, string $ulid): StreamedResponse
    {
        $user = Auth::user();
        $conversation = Conversation::where('ulid', $ulid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // 1. Enforce 5-hour limit
        $limit5h = (int) $this->getPlanSetting('max_messages_5h', 0);
        if ($limit5h > 0) {
            $count = \Addons\AiChatbot\Models\ConversationMessage::where('role', 'user')
                ->whereHas('conversation', fn($q) => $q->where('user_id', $user->id))
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
                ->whereHas('conversation', fn($q) => $q->where('user_id', $user->id))
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
                ->whereHas('conversation', fn($q) => $q->where('user_id', $user->id))
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
        if ($creditsPerMessage !== null && $creditsPerMessage !== '') {
            $requiredCredits = (float) $creditsPerMessage;
            if ($user->credits < $requiredCredits) {
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
            'product_slug' => 'nullable|string|exists:chatbot_products,slug',
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

        $product = null;
        if ($validated['product_slug'] ?? null) {
            $product = ChatbotProduct::where('slug', $validated['product_slug'])->active()->first();
        } elseif ($conversation->product_slug) {
            $product = ChatbotProduct::where('slug', $conversation->product_slug)->active()->first();
        }

        $model = $validated['model'] ?? $conversation->model ?? $product?->default_model ?? 'gpt-4o-mini';

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

        $systemPrompt = $product?->system_prompt ?? null;

        // Inject user's custom instructions if they exist
        if ($user->chat_custom_instructions) {
            $customInstructions = "\n\nUser's Custom Instructions:\n" . $user->chat_custom_instructions;
            $systemPrompt = ($systemPrompt ?? '') . $customInstructions;
        }

        // Inject knowledge base context if requested
        $kbSources = [];
        if (!empty($validated['use_knowledge_base']) && class_exists(\Addons\PublicKnowledgeBase\Services\KbSearchService::class)) {
            try {
                $kbService = app(\Addons\PublicKnowledgeBase\Services\KbSearchService::class);
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

        $productSwitch = null;
        if ($product && $conversation->product_slug !== $product->slug) {
            $productSwitch = $product->slug;
            $conversation->update(['product_slug' => $product->slug]);
        }

        $attachments = $validated['attachments'] ?? null;

        $userMsg = $conversation->messages()->create([
            'role' => 'user',
            'content' => $validated['content'],
            'model' => $model,
            'product_switch' => $productSwitch,
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

        $provider = $model ? $this->resolveProvider($model) : 'openai';

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

        return response()->stream(function () use (
            $conversation, $user, $model, $provider, $messages, $kbSources

        ) {
            $fullContent = '';

            // Emit KB sources before streaming tokens
            if (!empty($kbSources)) {
                echo 'data: '.json_encode(['type' => 'kb_sources', 'sources' => $kbSources])."\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

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
                if ($creditsPerMessage !== null && $creditsPerMessage !== '') {
                    $credits = (float) $creditsPerMessage;
                    if ($success) {
                        $user->deductCredits($credits, "AI Chatbot message: {$model}", [
                            'provider' => $provider,
                            'model' => $model,
                            'input_tokens' => $inputTokens,
                            'output_tokens' => $outputTokens,
                        ]);
                    }

                    TokenGuard::after(
                        $user,
                        $inputTokens,
                        $outputTokens,
                        $model,
                        $provider,
                        'chat',
                        ['conversation_ulid' => $conversation->ulid],
                        false, // don't charge since we did
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
                        true,
                        $success,
                        $responseTime
                    );
                }

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

    private function resolveProvider(string $model): string
    {
        return match (true) {
            str_contains($model, 'claude') => 'anthropic',
            str_contains($model, 'gemini') => 'google',
            str_contains($model, 'deepseek') => 'deepseek',
            str_contains($model, 'perplexity') => 'perplexity',
            str_contains($model, 'mistral') => 'mistral',
            str_contains($model, 'dall-e') => 'openai',
            default => 'openai',
        };
    }

    private function estimateTokens(array $messages): int
    {
        $text = '';
        foreach ($messages as $msg) {
            $text .= ($msg['content'] ?? '').' ';
        }

        return max(1, (int) (str_word_count($text, 0) * 1.3));
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

        return $message;
    }

    private function getPlanSetting(string $key, $default = null)
    {
        $user = Auth::user();
        $plan = $user?->plan;

        if ($plan && !$plan->is_free) {
            $planKey = "plan_{$plan->slug}_{$key}";
            $val = addon_setting('ai-chatbot', $planKey);
            if ($val !== null && $val !== '') {
                if ($val === 'true' || $val === '1') return true;
                if ($val === 'false' || $val === '0') return false;
                return $val;
            }
            
            // Fallback to legacy pro setting
            $legacyKey = "pro_{$key}";
            $legacyVal = addon_setting('ai-chatbot', $legacyKey);
            if ($legacyVal !== null && $legacyVal !== '') {
                if ($legacyVal === 'true' || $legacyVal === '1') return true;
                if ($legacyVal === 'false' || $legacyVal === '0') return false;
                return $legacyVal;
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

        return $default;
    }
}
