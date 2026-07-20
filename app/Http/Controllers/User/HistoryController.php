<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

use App\Models\GenerationHistory;
use App\Services\GenerationHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function __construct(
        private readonly GenerationHistoryService $historyService,
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $toolSlug = $request->query('tool_slug');

        $history = $this->historyService->getHistory($user, $toolSlug, 20);

        return Inertia::render('User/History', [
            'history' => $history->items(),
            'pagination' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'total' => $history->total(),
                'links' => $history->linkCollection(),
            ],
            'filters' => $request->only(['tool_slug']),
        ]);
    }

    public function byTool(string $toolSlug): Response
    {
        $user = Auth::user();
        $history = $this->historyService->getHistory($user, $toolSlug, 20);

        return Inertia::render('User/History', [
            'history' => $history->items(),
            'pagination' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'total' => $history->total(),
                'links' => $history->linkCollection(),
            ],
            'filters' => ['tool_slug' => $toolSlug],
        ]);
    }

    public function restore(GenerationHistory $history): JsonResponse
    {
        if ($history->user_id !== Auth::id()) {
            abort(403);
        }

        return response()->json($this->historyService->restore($history));
    }

    public function favorite(GenerationHistory $history): \Illuminate\Http\RedirectResponse
    {
        if ($history->user_id !== Auth::id()) {
            abort(403);
        }

        $this->historyService->toggleFavorite($history);

        return back();
    }

    public function diff(GenerationHistory $historyA, GenerationHistory $historyB): JsonResponse
    {
        if ($historyA->user_id !== Auth::id() || $historyB->user_id !== Auth::id()) {
            abort(403);
        }

        return response()->json($this->historyService->diff($historyA, $historyB));
    }

    public function label(Request $request, GenerationHistory $history): \Illuminate\Http\RedirectResponse
    {
        if ($history->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'label' => 'required|string|max:100',
        ]);

        $history->update(['label' => $validated['label']]);

        return back();
    }

    public function destroy(GenerationHistory $history): \Illuminate\Http\RedirectResponse
    {
        if ($history->user_id !== Auth::id()) {
            abort(403);
        }

        $history->delete();

        return back();
    }
}
