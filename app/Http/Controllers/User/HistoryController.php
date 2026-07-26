<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;

use App\Models\AiTool;
use App\Models\GenerationHistory;
use App\Services\GenerationHistoryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
        $starredOnly = $request->boolean('starred');

        return Inertia::render(
            'User/History',
            $this->historyPayload(
                $this->historyService->getHistory($user, $request->query('tool_slug'), 20, $starredOnly),
                ['tool_slug' => $request->query('tool_slug'), 'starred' => $starredOnly],
                $user
            )
        );
    }

    public function byTool(Request $request, string $toolSlug): Response
    {
        $user = Auth::user();
        $starredOnly = $request->boolean('starred');

        return Inertia::render(
            'User/History',
            $this->historyPayload(
                $this->historyService->getHistory($user, $toolSlug, 20, $starredOnly),
                ['tool_slug' => $toolSlug, 'starred' => $starredOnly],
                $user
            )
        );
    }

    /**
     * Rows carry a tool_slug; the list has to show the tool's NAME. Resolved in one query
     * for the page rather than per row, with the slug kept as the fallback for a tool that
     * has since been deleted.
     *
     * @param  array{tool_slug: string|null, starred: bool}  $filters
     * @return array<string, mixed>
     */
    private function historyPayload(LengthAwarePaginator $history, array $filters, $user): array
    {
        $items = collect($history->items());
        $toolNames = AiTool::whereIn('slug', $items->pluck('tool_slug')->filter()->unique())
            ->pluck('name', 'slug');

        return [
            'history' => $items
                ->map(fn (GenerationHistory $item) => [
                    ...$item->toArray(),
                    'tool_name' => $toolNames[$item->tool_slug] ?? $item->tool_slug,
                ])
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'total' => $history->total(),
                // from/to drive the shared Pagination component's "showing X to Y of Z".
                'from' => $history->firstItem(),
                'to' => $history->lastItem(),
                // withQueryString(), or paging out of the Starred tab silently drops the
                // filter and lands the user back in the full list.
                'links' => $history->withQueryString()->linkCollection(),
            ],
            // Both counted over the whole account, not the current page — the tab badges
            // claimed "3 starred" when they meant "3 starred among the 20 rows on this page",
            // and the All badge dropped to 20 as soon as you had more than one page.
            'starredCount' => GenerationHistory::where('user_id', $user->id)
                ->where('is_favorited', true)
                ->count(),
            'totalCount' => GenerationHistory::where('user_id', $user->id)->count(),
            'filters' => $filters,
        ];
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
