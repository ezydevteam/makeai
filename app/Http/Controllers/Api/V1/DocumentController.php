<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
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
            'slug' => 'required|string|max:100|exists:ai_tools,slug',
            'title' => 'nullable|string|max:255',
            'content' => 'required|string|max:200000',
        ]);

        $template = AiTool::where('slug', $data['slug'])->firstOrFail();

        $document = Document::create([
            'user_id' => $request->user()->id,
            'title' => $data['title'] ?: $template->name.' Output',
            'content' => $data['content'],
            'tool_slug' => $template->slug,
            'word_count' => Document::calculateWordCount($data['content']),
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

        $toolName = null;
        if ($document->tool_slug) {
            $tool = AiTool::where('slug', $document->tool_slug)->first();
            $toolName = $tool?->name;
        }

        return Inertia::render('AI/DocumentEditor', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'content' => $document->content,
                'tool_slug' => $document->tool_slug,
                'tool_name' => $toolName,
                'word_count' => $document->word_count,
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
        ]);

        $document->update([
            'title' => $data['title'],
            'content' => $data['content'],
            'word_count' => Document::calculateWordCount($data['content']),
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

    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = $request->input('search');

        $documents = Document::query()
            ->where('user_id', $user->id)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('tool_slug', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $mappedDocuments = collect($documents->items())->map(fn ($document) => [
            'id' => $document->id,
            'title' => $document->title,
            'tool_slug' => $document->tool_slug,
            'word_count' => $document->word_count,
            'created_at' => $document->created_at?->toISOString(),
            'updated_at' => $document->updated_at?->toISOString(),
        ])->all();

        return Inertia::render('User/Documents', [
            'documents' => $mappedDocuments,
            'filters' => [
                'search' => $search,
            ],
            'pagination' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'total' => $documents->total(),
                'links' => $documents->linkCollection(),
            ],
        ]);
    }

    public function destroy(Request $request, Document $document)
    {
        abort_unless($document->user_id === $request->user()->id, 403);

        $document->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => translate('Document deleted successfully.'),
            ]);
        }

        return back()->with('success', translate('Document deleted successfully.'));
    }
}
