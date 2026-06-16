<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatProjectController extends Controller
{
    public function index(): JsonResponse
    {
        $projects = Auth::user()->chatProjects()
            ->withCount('conversations')
            ->latest('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color_hex' => 'nullable|string|max:7',
        ]);

        $project = Auth::user()->chatProjects()->create($validated);

        return response()->json([
            'success' => true,
            'data' => $project,
        ], 201);
    }

    public function update(Request $request, ChatProject $project): JsonResponse
    {
        if ($project->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color_hex' => 'nullable|string|max:7',
        ]);

        $project->update($validated);

        return response()->json([
            'success' => true,
            'data' => $project,
        ]);
    }

    public function destroy(ChatProject $project): JsonResponse
    {
        if ($project->user_id !== Auth::id()) {
            abort(403);
        }

        $project->conversations()->update(['project_id' => null]);
        $project->delete();

        return response()->json([
            'success' => true,
            'message' => translate('Project deleted.'),
        ]);
    }
}
