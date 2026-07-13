<?php

namespace Addons\AiVideoCreator\Http\Controllers\User;

use Addons\AiVideoCreator\Jobs\GenerateAvatarVideo;
use Addons\AiVideoCreator\Jobs\GenerateImageToVideo;
use Addons\AiVideoCreator\Jobs\GenerateSlideshow;
use Addons\AiVideoCreator\Jobs\GenerateTextToVideo;
use Addons\AiVideoCreator\Models\VcProject;
use Addons\AiVideoCreator\Services\VideoProviderService;
use App\Services\ContentModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class VideoCreatorController extends Controller
{
    public function create(): \Inertia\Response
    {
        $projects = VcProject::where('user_id', auth()->id())->get(['id', 'ulid', 'name']);

        return inertia('Addons/ai-video-creator/User/Creator', [
            'projects' => $projects,
            'credit_costs' => [
                'text_video' => (int) addon_setting('ai-video-creator', 'credits_text_video', 50),
                'text_video_long' => (int) addon_setting('ai-video-creator', 'credits_text_video_long', 100),
                'image_video' => (int) addon_setting('ai-video-creator', 'credits_image_video', 40),
                'avatar_video' => (int) addon_setting('ai-video-creator', 'credits_avatar_video', 80),
                'slideshow' => (int) addon_setting('ai-video-creator', 'credits_slideshow', 30),
            ],
            'max_video_duration' => (int) addon_setting('ai-video-creator', 'max_video_duration', 30),
            'max_storage_mb_per_user' => (int) addon_setting('ai-video-creator', 'max_storage_mb_per_user', 500),
        ]);
    }

    public function store(Request $request, VideoProviderService $providerService): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:text_to_video,image_to_video,avatar_video,slideshow'],
            'prompt' => ['nullable', 'string', 'max:2000'],
            'script' => ['nullable', 'string', 'max:5000'],
            'duration' => ['nullable', 'integer', 'in:5,10'],
            'aspect_ratio' => ['nullable', 'in:16:9,9:16,1:1'],
            'project_id' => ['nullable', 'exists:vc_projects,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'slides' => ['nullable', 'array', 'min:2', 'max:20'],
            'slides.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'slide_duration' => ['nullable', 'integer', 'min:1', 'max:10'],
            'voice_id' => ['nullable', 'string'],
            'music_volume' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        // Content safety gate — reject unsafe prompts/scripts before a render is
        // created and credits are charged. No-op unless the Content Moderation
        // extension is enabled in `block` mode.
        $moderationText = trim(($validated['prompt'] ?? '') . "\n" . ($validated['script'] ?? ''));
        if (ContentModerationService::fromSettings()->textViolates($moderationText, 'video-creator')) {
            return response()->json([
                'success' => false,
                'message' => translate('This request was blocked by content safety filters.'),
            ], 422);
        }

        if ($validated['project_id'] ?? null) {
            $project = VcProject::find($validated['project_id']);
            abort_if($project->user_id !== auth()->id(), 403);
        }

        $inputMediaPath = null;
        $slideshowParams = [];

        if (($validated['type'] ?? '') === 'image_to_video' && $request->hasFile('image')) {
            $file = $request->file('image');
            $inputMediaPath = $file->store('video-creator/' . auth()->id() . '/inputs', 'local');
        }

        if (($validated['type'] ?? '') === 'slideshow' && $request->hasFile('slides')) {
            $imagePaths = [];
            foreach ($request->file('slides') as $slide) {
                $imagePaths[] = $slide->store('video-creator/' . auth()->id() . '/inputs', 'local');
            }
            $slideshowParams = [
                'images' => $imagePaths,
                'script' => $validated['script'] ?? null,
                'voice_id' => $validated['voice_id'] ?? null,
                'slide_duration' => $validated['slide_duration'] ?? 3,
                'music_volume' => $validated['music_volume'] ?? 0.3,
            ];
        }

        $render = $providerService->createRender(auth()->user(), [
            'type' => $validated['type'],
            'project_id' => $validated['project_id'] ?? null,
            'title' => $validated['title'] ?? null,
            'prompt' => $validated['prompt'] ?? null,
            'script' => $validated['script'] ?? null,
            'duration' => $validated['duration'] ?? 5,
            'aspect_ratio' => $validated['aspect_ratio'] ?? '16:9',
            'input_media_path' => $inputMediaPath,
            'provider_settings' => array_merge($slideshowParams),
        ]);

        $job = match ($render->type) {
            'text_to_video' => new GenerateTextToVideo($render->id),
            'image_to_video' => new GenerateImageToVideo($render->id),
            'avatar_video' => new GenerateAvatarVideo($render->id),
            'slideshow' => new GenerateSlideshow($render->id),
        };

        dispatch($job->onQueue('ai'));

        return response()->json([
            'render_id' => $render->id,
            'ulid' => $render->ulid,
            'status' => 'queued',
        ]);
    }
}
