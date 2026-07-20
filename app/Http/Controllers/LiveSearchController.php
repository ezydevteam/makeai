<?php

namespace App\Http\Controllers;

use App\Services\LiveSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveSearchController extends Controller
{
    public function __invoke(Request $request, LiveSearchService $search): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'context' => ['nullable', 'string', 'in:public,admin'],
        ]);

        $term = trim((string) ($validated['q'] ?? ''));
        $isAdminContext = ($validated['context'] ?? 'public') === 'admin' && auth('admin')->check();

        return response()->json([
            'success' => true,
            'data' => [
                'groups' => $isAdminContext
                    ? $search->searchAdmin($term, auth('admin')->user())
                    : $search->searchPublic($term),
                'suggestions' => $search->suggestions($isAdminContext ? 'admin' : 'public'),
            ],
            'message' => translate('Search results loaded.'),
        ]);
    }
}
