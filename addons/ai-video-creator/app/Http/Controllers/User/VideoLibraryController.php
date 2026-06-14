<?php

namespace Addons\AiVideoCreator\Http\Controllers\User;

use Addons\AiVideoCreator\Models\VcFolder;
use Addons\AiVideoCreator\Models\VcProject;
use Addons\AiVideoCreator\Models\VcRender;
use Addons\AiVideoCreator\Models\VcSubtitle;
use Addons\AiVideoCreator\Services\SubtitleService;
use Addons\AiVideoCreator\Services\TrimmerService;
use Addons\AiVideoCreator\Services\VideoProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class VideoLibraryController extends Controller
{
    public function index(): \Inertia\Response
    {
        $userId = auth()->id();

        $folders = VcFolder::forUser($userId)
            ->withCount('projects')
            ->orderBy('sort_order')
            ->get();

        $projects = VcProject::where('user_id', $userId)
            ->with(['folder', 'renders' => fn ($q) => $q->latest()->limit(4)])
            ->withCount('renders')
            ->when(request('folder_id'), fn ($q, $id) => $q->where('folder_id', $id))
            ->latest()
            ->paginate(12);

        $recentRenders = VcRender::forUser($userId)
            ->completed()
            ->latest()
            ->limit(8)
            ->get();

        return inertia('Addons/ai-video-creator/User/Library', [
            'folders' => $folders,
            'projects' => $projects,
            'recent_renders' => $recentRenders->map(fn ($r) => $this->formatRender($r)),
        ]);
    }

    public function storeFolder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-f]{6}$/i'],
        ]);

        VcFolder::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#6366f1',
        ]);

        return back()->with('flash', translate('Folder created.'));
    }

    public function storeProject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'folder_id' => ['nullable', 'exists:vc_folders,id'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-f]{6}$/i'],
        ]);

        if ($validated['folder_id'] ?? null) {
            $folder = VcFolder::find($validated['folder_id']);
            abort_if($folder->user_id !== auth()->id(), 403);
        }

        VcProject::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'folder_id' => $validated['folder_id'] ?? null,
            'color' => $validated['color'] ?? '#6366f1',
        ]);

        return back()->with('flash', translate('Project created.'));
    }

    public function destroyRender(VcRender $render): RedirectResponse
    {
        abort_if($render->user_id !== auth()->id(), 403);
        abort_if($render->status === 'processing', 422, 'Cannot delete a render in progress.');

        if ($render->file_path) {
            Storage::delete($render->file_path);
        }
        if ($render->thumbnail_path) {
            Storage::delete($render->thumbnail_path);
        }
        $render->delete();

        return back()->with('flash', translate('Render deleted.'));
    }

    private function formatRender(VcRender $render): array
    {
        return [
            'id' => $render->id,
            'ulid' => $render->ulid,
            'type' => $render->type,
            'type_label' => $render->type_label,
            'status' => $render->status,
            'status_label' => $render->status_label,
            'title' => $render->title,
            'provider' => $render->provider,
            'duration_seconds' => $render->duration_seconds,
            'duration_actual' => $render->duration_actual,
            'thumbnail_url' => $render->thumbnail_url,
            'file_url' => $render->file_url,
            'share_enabled' => $render->share_enabled,
            'share_token' => $render->share_token,
            'credits_deducted' => $render->credits_deducted,
            'completed_at' => $render->completed_at?->toISOString(),
            'created_at' => $render->created_at->toISOString(),
        ];
    }
}
