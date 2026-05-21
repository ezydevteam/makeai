<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiTemplate;
use App\Models\Document;
use App\Services\NotificationEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => 'required|string|max:100|exists:ai_templates,slug',
            'title' => 'nullable|string|max:255',
            'content' => 'required|string|max:200000',
            'folder_id' => 'nullable|integer',
        ]);

        $template = AiTemplate::where('slug', $data['slug'])->firstOrFail();

        $document = Document::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'] ?: $template->name.' Output',
            'content' => $data['content'],
            'tool_slug' => $template->slug,
            'word_count' => str_word_count(strip_tags($data['content'])),
            'folder_id' => $data['folder_id'] ?? null,
        ]);

        app(NotificationEventService::class)->documentReady($document);

        return response()->json([
            'success' => true,
            'message' => translate('Document saved successfully.'),
            'data' => [
                'id' => $document->id,
                'title' => $document->title,
            ],
        ], 201);
    }

    public function edit(Request $request, Document $document): Response
    {
        abort_unless($document->user_id === $request->user()->id, 403);

        return Inertia::render('AI/DocumentEditor', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'content' => $document->content,
                'tool_slug' => $document->tool_slug,
                'word_count' => $document->word_count,
                'folder_id' => $document->folder_id,
                'updated_at' => optional($document->updated_at)->toISOString(),
            ],
        ]);
    }

    public function update(Request $request, Document $document)
    {
        abort_unless($document->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:200000',
            'folder_id' => 'nullable|integer',
        ]);

        $document->update([
            'title' => $data['title'],
            'content' => $data['content'],
            'folder_id' => $data['folder_id'] ?? null,
            'word_count' => str_word_count(strip_tags($data['content'])),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => translate('Document updated successfully.'),
                'data' => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'word_count' => $document->word_count,
                ],
            ]);
        }

        return back()->with('success', translate('Document updated successfully.'));
    }
}
