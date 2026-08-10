<?php

namespace App\Http\Controllers;

use App\Models\AiTool;
use App\Models\KnowledgeBase;
use App\Models\RagSession;
use App\Services\AI\AiErrors;
use App\Services\AI\ToolAccessService;
use App\Services\RagToolService;
use App\Services\Security\SsrfGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RagToolController extends Controller
{
    public function __construct(
        private RagToolService $ragToolService,
        private ToolAccessService $toolAccess,
    ) {}

    /**
     * Render the RAG tool page.
     */
    public function show(string $slug): Response
    {
        $tool = AiTool::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        abort_if($tool->type !== 'rag', 404);

        $user = Auth::user();

        // Enforce the tool's access level (premium / plan gating)
        $this->toolAccess->assertCanUse($tool, $user);

        $recentSessions = $user
            ? RagSession::where('user_id', $user->id)
                ->where('tool_slug', $slug)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn (RagSession $s) => [
                    'id' => $s->id,
                    'title' => $s->title,
                    'status' => $s->status,
                    'source_meta' => $s->source_meta,
                    'created_at' => $s->created_at->toIso8601String(),
                ])
            : collect();

        return Inertia::render('Tools/RagTool', [
            'tool' => [
                'id' => $tool->id,
                'ulid' => $tool->ulid,
                'name' => $tool->name,
                'slug' => $tool->slug,
                'description' => $tool->description,
                'icon' => $tool->icon,
                'color' => $tool->color,
                'fields' => is_string($tool->fields) ? json_decode($tool->fields, true) : $tool->fields,
                'show_header' => (bool) $tool->show_header,
                'show_footer' => (bool) $tool->show_footer,
            ],
            'recentSessions' => $recentSessions,
            'ragSettings' => [
                'max_file_mb' => (int) settings('rag_max_file_mb', 25),
                'max_pages' => (int) settings('rag_max_pages', 300),
                'allowed_file_types' => $tool->fields['accept'] ?? [],
                'multi_file' => $tool->fields['multi_file'] ?? false,
                'min_files' => (int) ($tool->fields['min_files'] ?? 2),
                'max_files' => (int) ($tool->fields['max_files'] ?? 3),
            ],
        ]);
    }

    /**
     * Create a new RAG session and dispatch ingestion.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $tool = AiTool::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        abort_if($tool->type !== 'rag', 404);

        $user = Auth::user();

        // Enforce the tool's access level (premium / plan gating)
        $this->toolAccess->assertCanUse($tool, $user);

        $fields = $tool->fields ?? [];
        $sourceType = $fields['source_type'] ?? 'file';

        // Rate limit ingestion
        $rateKey = "rag-ingest:{$user->id}";
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return response()->json([
                'message' => translate('Too many ingestion requests. Please wait a moment.'),
            ], 429);
        }
        RateLimiter::hit($rateKey, 60);

        $input = [];

        switch ($sourceType) {
            case 'file':
                $maxMb = (int) settings('rag_max_file_mb', 25);
                $allowedTypes = $fields['accept'] ?? ['pdf', 'docx', 'txt', 'md'];

                if ($fields['multi_file'] ?? false) {
                    $request->validate([
                        'files' => 'required|array|min:' . ($fields['min_files'] ?? 2) . '|max:' . ($fields['max_files'] ?? 3),
                        'files.*' => 'file|mimes:' . implode(',', $allowedTypes) . '|max:' . ($maxMb * 1024),
                        'title' => 'nullable|string|max:255',
                    ]);

                    $input['files'] = $request->file('files');
                    $input['title'] = $request->input('title', 'Multi-Document Session');
                } else {
                    $request->validate([
                        'file' => 'required|file|mimes:' . implode(',', $allowedTypes) . '|max:' . ($maxMb * 1024),
                        'title' => 'nullable|string|max:255',
                    ]);

                    $input['file'] = $request->file('file');
                    $input['title'] = $request->input('title', $request->file('file')->getClientOriginalName());
                }
                break;

            case 'url':
                $request->validate([
                    'url' => 'required|url:http,https|max:2048',
                    'title' => 'nullable|string|max:255',
                ]);

                try {
                    SsrfGuard::assertPublicUrl($request->input('url'));
                } catch (\RuntimeException $e) {
                    return response()->json(['message' => $e->getMessage()], 422);
                }

                $input['url'] = $request->input('url');
                $input['title'] = $request->input('title', $request->input('url'));
                break;

            case 'youtube':
                $request->validate([
                    'url' => ['required', 'string', 'max:2048', 'regex:#^https?://(www\.|m\.)?(youtube\.com|youtu\.be)/#i'],
                    'title' => 'nullable|string|max:255',
                ]);
                $input['url'] = $request->input('url');
                $input['title'] = $request->input('title');
                break;

            case 'collection':
                $request->validate([
                    'knowledge_base_id' => 'required|integer|exists:knowledge_bases,id',
                ]);

                $kb = KnowledgeBase::where('id', $request->input('knowledge_base_id'))
                    ->where('user_id', $user->id)
                    ->where('is_ephemeral', false)
                    ->first();

                if (! $kb) {
                    return response()->json([
                        'message' => translate('Knowledge base not found or not accessible.'),
                    ], 404);
                }

                $input['knowledge_base_id'] = $kb->id;
                $input['title'] = $request->input('title', $kb->name);
                break;
        }

        try {
            $session = $this->ragToolService->createSession($user, $tool, $input);

            return response()->json([
                'session' => $this->ragToolService->sessionStatus($session),
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get session ingestion status.
     */
    public function status(string $ulid): JsonResponse
    {
        $session = RagSession::where('id', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json($this->ragToolService->sessionStatus($session));
    }

    /**
     * Serve the RAG document file for preview.
     */
    public function file(string $ulid)
    {
        $session = RagSession::where('id', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Read through the disk the resolver found the file on: uploads live on
        // the private `local` disk now, while sessions predating that change
        // still point at the default disk. Assuming the default here 404s every
        // new upload.
        $location = $this->ragToolService->getSessionFileLocation($session);
        if (! $location) {
            abort(404);
        }

        return \Illuminate\Support\Facades\Storage::disk($location['disk'])->response($location['path']);
    }

    /**
     * Reopen a past session.
     */
    public function session(string $ulid): JsonResponse
    {
        $session = RagSession::where('id', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $messages = $session->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'role' => $msg->role,
                'content' => $msg->content,
                'sources' => $msg->sources,
                'created_at' => $msg->created_at->toIso8601String(),
            ]);

        return response()->json([
            'session' => $this->ragToolService->sessionStatus($session),
            'messages' => $messages,
        ]);
    }

    /**
     * SSE chat streaming endpoint.
     */
    public function chat(Request $request, string $ulid): StreamedResponse
    {
        $session = RagSession::where('id', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($session->status !== 'ready') {
            abort(422, translate('Session is not ready for chat yet.'));
        }

        // Enforce the tool's access level (premium / plan gating)
        $tool = AiTool::where('slug', $session->tool_slug)->where('is_active', true)->first();
        if ($tool) {
            $this->toolAccess->assertCanUse($tool, Auth::user());
        }

        $validated = $request->validate([
            'message' => 'required|string|max:16000',
            'mode' => 'nullable|string|in:chat,compare,summarize,kb_write',
            'aspect' => 'nullable|string|max:500',
            'topic' => 'nullable|string|max:500',
            'content_type' => 'nullable|string|max:100',
            'tone' => 'nullable|string|max:50',
            'length' => 'nullable|string|in:short,medium,long',
        ]);

        $user = Auth::user();
        $mode = $validated['mode'] ?? 'chat';

        return response()->stream(function () use ($session, $validated, $user, $mode) {
            try {
                $stream = match ($mode) {
                    'compare' => $this->ragToolService->streamCompare($session, $validated['aspect'] ?? '', $user),
                    'summarize' => $this->ragToolService->summarizeLong($session, [
                        'length' => $validated['length'] ?? 'medium',
                    ], $user),
                    'kb_write' => $this->ragToolService->streamKbWrite($session, [
                        'topic' => $validated['topic'] ?? '',
                        'content_type' => $validated['content_type'] ?? 'article',
                        'tone' => $validated['tone'] ?? 'professional',
                        'length' => $validated['length'] ?? 'medium',
                    ], $user),
                    default => $this->ragToolService->streamQuery($session, $validated['message'], $user),
                };

                foreach ($stream as $event) {
                    echo 'data: '.json_encode($event)."\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();

                    if (connection_aborted()) {
                        break;
                    }
                }
            } catch (\Throwable $e) {
                \Log::error('RAG chat stream failed', ['session' => $session->id, 'error' => $e->getMessage()]);
                echo 'data: '.json_encode(['type' => 'error', 'message' => AiErrors::sanitize($e->getMessage())])."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Save ephemeral KB as permanent.
     */
    public function saveToKb(Request $request, string $ulid): JsonResponse
    {
        $session = RagSession::where('id', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $kb = $this->ragToolService->saveToKnowledgeBase($session, $validated['name']);

        return response()->json([
            'message' => translate('Saved to Knowledge Base.'),
            'knowledge_base' => [
                'id' => $kb->id,
                'name' => $kb->name,
            ],
        ]);
    }

    /**
     * Delete session.
     */
    public function destroy(string $ulid): JsonResponse
    {
        $session = RagSession::where('id', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $this->ragToolService->deleteSession($session);

        return response()->json([
            'message' => translate('Session deleted.'),
        ]);
    }

    /**
     * Update/Rename session.
     */
    public function update(Request $request, string $ulid): JsonResponse
    {
        $session = RagSession::where('id', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $session->update([
            'title' => $validated['title'],
        ]);

        return response()->json([
            'message' => translate('Session renamed successfully.'),
            'session' => [
                'id' => $session->id,
                'title' => $session->title,
                'status' => $session->status,
                'source_meta' => $session->source_meta,
                'created_at' => $session->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get current share link status.
     */
    public function getShareStatus(string $ulid): JsonResponse
    {
        $session = RagSession::where('id', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $url = $session->share_token ? url("/shared/rag/{$session->share_token}") : null;

        return response()->json([
            'share_url' => $url,
            'share_token' => $session->share_token,
        ]);
    }

    /**
     * Get or create share token.
     */
    public function share(string $ulid): JsonResponse
    {
        $session = RagSession::where('id', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $token = $session->share_token ?? $this->ragToolService->createShareToken($session);
        $url = url("/shared/rag/{$token}");

        return response()->json([
            'share_url' => $url,
            'share_token' => $token,
        ]);
    }

    /**
     * Revoke share token.
     */
    public function unshare(string $ulid): JsonResponse
    {
        $session = RagSession::where('id', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $session->update(['share_token' => null]);

        return response()->json([
            'message' => translate('Share link revoked.'),
        ]);
    }

    /**
     * Public read-only view of a shared session.
     */
    public function sharedView(string $token): Response
    {
        $session = RagSession::where('share_token', $token)->firstOrFail();

        $messages = $session->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'role' => $msg->role,
                'content' => $msg->content,
                'sources' => $msg->sources,
            ]);

        return Inertia::render('Tools/RagShared', [
            'session' => [
                'title' => $session->title,
                'tool_slug' => $session->tool_slug,
                'source_meta' => $session->source_meta,
                'created_at' => $session->created_at->toIso8601String(),
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Export chat session as Markdown.
     */
    public function exportChat(string $ulid): \Illuminate\Http\Response
    {
        $session = RagSession::where('id', $ulid)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $messages = $session->messages()->orderBy('created_at')->get();

        $md = "# {$session->title}\n\n";
        $md .= "> Source: {$session->tool_slug}\n";
        $md .= "> Date: {$session->created_at->format('Y-m-d H:i')}\n\n---\n\n";

        foreach ($messages as $msg) {
            $role = $msg->role === 'user' ? 'You' : 'AI';
            $md .= "### {$role}\n\n{$msg->content}\n\n";

            if ($msg->sources && $msg->role === 'assistant') {
                $md .= "*Sources:*\n";
                foreach ($msg->sources as $source) {
                    $md .= "- {$source['doc']}";
                    if (isset($source['score'])) {
                        $md .= " (score: {$source['score']})";
                    }
                    $md .= "\n";
                }
                $md .= "\n";
            }

            $md .= "---\n\n";
        }

        $filename = Str::slug($session->title ?? 'rag-chat').'.md';

        return response($md, 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
