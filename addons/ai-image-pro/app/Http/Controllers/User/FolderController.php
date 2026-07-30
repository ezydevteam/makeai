<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Http\Controllers\User;

use Addons\AiImagePro\Models\AipFolder;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Library folders. The route group requires auth, so every folder is scoped to the
 * authenticated user; update/destroy re-check ownership before touching a row bound
 * by id.
 */
class FolderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);

        $folder = AipFolder::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#6366f1',
            'sort' => (int) ($validated['sort'] ?? 0),
        ]);

        return response()->json([
            'success' => true,
            'folder' => $folder,
        ], 201);
    }

    public function update(Request $request, AipFolder $folder): JsonResponse
    {
        $this->authorizeFolder($folder, $request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);

        $folder->update([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? $folder->color,
            'sort' => array_key_exists('sort', $validated) && $validated['sort'] !== null
                ? (int) $validated['sort']
                : $folder->sort,
        ]);

        return response()->json([
            'success' => true,
            'folder' => $folder->fresh(),
        ]);
    }

    public function destroy(Request $request, AipFolder $folder): JsonResponse
    {
        $this->authorizeFolder($folder, $request);

        // Assets keep existing; the FK is nullOnDelete, so they simply return to the
        // unfiled pool rather than being destroyed with their folder.
        $folder->delete();

        return response()->json(['success' => true]);
    }

    private function authorizeFolder(AipFolder $folder, Request $request): void
    {
        if ($folder->user_id !== $request->user()?->id) {
            abort(403);
        }
    }
}
