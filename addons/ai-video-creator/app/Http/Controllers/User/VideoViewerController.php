<?php

namespace Addons\AiVideoCreator\Http\Controllers\User;

use Addons\AiVideoCreator\Models\VcRender;
use Addons\AiVideoCreator\Services\SubtitleService;
use Addons\AiVideoCreator\Services\TrimmerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class VideoViewerController extends Controller
{
    public function show(VcRender $render): \Inertia\Response
    {
        abort_if($render->user_id !== auth()->id(), 403);

        $render->load('subtitles', 'project');

        return inertia('Addons/ai-video-creator/User/Viewer', [
            'render' => $this->formatRender($render),
        ]);
    }

    public function status(VcRender $render): JsonResponse
    {
        abort_if($render->user_id !== auth()->id() && ! $render->share_enabled, 403);

        return response()->json([
            'status' => $render->status,
            'file_url' => $render->file_url,
            'thumbnail_url' => $render->thumbnail_url,
            'error_message' => $render->error_message,
            'completed_at' => $render->completed_at?->toISOString(),
        ]);
    }

    public function generateSubtitles(Request $request, VcRender $render): JsonResponse
    {
        abort_if($render->user_id !== auth()->id(), 403);

        if ($render->status !== 'completed') {
            return response()->json(['error' => 'Video must be completed first.'], 422);
        }

        $validated = $request->validate([
            'format' => ['required', 'in:srt,vtt,json'],
            'language' => ['nullable', 'string', 'max:5'],
        ]);

        $subtitle = app(SubtitleService::class)->generate(
            $render,
            $validated['format'],
            $validated['language'] ?? 'en',
        );

        return response()->json([
            'id' => $subtitle->id,
            'status' => $subtitle->status,
            'format' => $subtitle->format,
            'language' => $subtitle->language,
        ]);
    }

    public function trim(Request $request, VcRender $render): JsonResponse
    {
        abort_if($render->user_id !== auth()->id(), 403);

        if ($render->status !== 'completed') {
            return response()->json(['error' => 'Video must be completed first.'], 422);
        }

        $validated = $request->validate([
            'start_seconds' => ['required', 'numeric', 'min:0'],
            'end_seconds' => ['required', 'numeric'],
        ]);

        app(TrimmerService::class)->trim($render, $validated['start_seconds'], $validated['end_seconds']);
        $render->refresh();

        return response()->json([
            'file_url' => $render->file_url,
            'duration_actual' => $render->duration_actual,
        ]);
    }

    public function toggleShare(VcRender $render): JsonResponse
    {
        abort_if($render->user_id !== auth()->id(), 403);

        $render->update(['share_enabled' => ! $render->share_enabled]);

        return response()->json([
            'share_enabled' => $render->share_enabled,
            'share_token' => $render->share_token,
        ]);
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
            'prompt' => $render->prompt,
            'provider' => $render->provider,
            'aspect_ratio' => $render->aspect_ratio,
            'duration_seconds' => $render->duration_seconds,
            'duration_actual' => $render->duration_actual,
            'file_url' => $render->file_url,
            'thumbnail_url' => $render->thumbnail_url,
            'file_size_bytes' => $render->file_size_bytes,
            'share_enabled' => $render->share_enabled,
            'share_token' => $render->share_token,
            'credits_deducted' => $render->credits_deducted,
            'error_message' => $render->error_message,
            'project' => $render->project ? ['name' => $render->project->name] : null,
            'subtitles' => $render->subtitles->map(fn ($s) => [
                'format' => $s->format,
                'status' => $s->status,
                'language' => $s->language,
                'content' => $s->content,
            ]),
            'completed_at' => $render->completed_at?->toISOString(),
            'created_at' => $render->created_at->toISOString(),
        ];
    }
}
