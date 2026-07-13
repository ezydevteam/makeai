<?php

declare(strict_types=1);

namespace Addons\AiImageEditor\Http\Controllers\User;

use Addons\AiImageEditor\Http\Requests\ImageEditRequest;
use Addons\AiImageEditor\Jobs\ApplyImageEdit;
use Addons\AiImageEditor\Models\IeEdit;
use Addons\AiImageEditor\Models\IeSession;
use Addons\AiImageEditor\Services\ImageEditorService;
use App\Services\ContentModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ImageEditorController extends \App\Http\Controllers\Controller
{
    public function show(Request $request): Response
    {
        // Resume existing session
        if (! $request->has('image') && ! $request->hasFile('upload')) {
            $session = IeSession::where('user_id', auth()->id())->latest()->first();

            if (! $session) {
                return $this->renderEditorWithNoSession();
            }

            return $this->renderEditor($session);
        }

        // Resolve source image
        if ($request->hasFile('upload')) {
            $maxSizeMb = (int) addon_setting('ai-image-editor', 'max_input_size_mb', 10);

            $request->validate([
                'upload' => 'required|file|mimes:jpg,jpeg,png,webp|max:' . ($maxSizeMb * 1024),
            ]);

            $sourcePath = $request->file('upload')->store(
                'image-editor/' . auth()->id() . '/sources',
                'public',
            );
            $sourceType = 'uploaded';
            $sourceImageId = null;
        } else {
            $imageId = $request->get('image');

            $generatedImage = DB::table('documents')
                ->where('user_id', auth()->id())
                ->where(function ($q) use ($imageId) {
                    $q->where('id', $imageId)->orWhere('ulid', $imageId);
                })
                ->first();

            abort_if(! $generatedImage, 404, translate('Image not found.'));

            // Extract the image path from the document content
            $content = json_decode($generatedImage->content ?? '{}', true);
            $sourcePath = $content['output_path'] ?? null;

            if (! $sourcePath || ! Storage::disk('public')->exists($sourcePath)) {
                abort(422, translate('Source image file is no longer available.'));
            }

            $sourceType = 'generated';
            $sourceImageId = $generatedImage->id;
        }

        // Create or replace session. Read dimensions from the bytes so this works whether
        // the public disk is local or an S3-compatible bucket (->path() has no meaning on S3).
        $sourceBinary = Storage::disk('public')->get($sourcePath);
        [$width, $height] = ($sourceBinary !== null ? @getimagesizefromstring($sourceBinary) : false) ?: [null, null];
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        // Delete previous session (cascades to edits)
        IeSession::where('user_id', auth()->id())->delete();

        $session = IeSession::create([
            'user_id' => auth()->id(),
            'source_type' => $sourceType,
            'source_image_id' => $sourceImageId ?? null,
            'source_path' => $sourcePath,
            'source_url' => Storage::disk('public')->url($sourcePath),
            'current_path' => $sourcePath,
            'current_url' => Storage::disk('public')->url($sourcePath),
            'width' => $width,
            'height' => $height,
            'format' => $ext,
        ]);

        return $this->renderEditor($session);
    }

    public function apply(ImageEditRequest $request): JsonResponse
    {
        $session = IeSession::where('user_id', auth()->id())->firstOrFail();

        // Content safety gate — reject unsafe edit prompts before charging credits.
        // No-op unless the Content Moderation extension is enabled in `block` mode.
        $editPrompt = (string) ($request->input('params.prompt') ?? '');
        if (ContentModerationService::fromSettings()->textViolates($editPrompt, 'image-editor')) {
            return response()->json([
                'success' => false,
                'message' => translate('This request was blocked by content safety filters.'),
            ], 422);
        }

        $service = app(ImageEditorService::class);
        $credits = $service->getCreditsForOperation($request->operation);

        if (! credit_quota_mode() && auth()->user()->credits < $credits) {
            return response()->json([
                'success' => false,
                'message' => translate('Insufficient credits.'),
            ], 402);
        }

        // Handle mask upload
        $maskPath = null;
        if ($request->hasFile('mask')) {
            $maskPath = $request->file('mask')->store(
                'image-editor/' . auth()->id() . '/masks',
                'local',
            );
        }

        // Handle style image upload
        $stylePath = null;
        if ($request->hasFile('style_image')) {
            $stylePath = $request->file('style_image')->store(
                'image-editor/' . auth()->id() . '/styles',
                'local',
            );
        }

        // Deduct credits
        if ($credits > 0) {
            deduct_credits(auth()->id(), $credits, 'Image edit: ' . $request->operation);
        }

        // Determine provider
        $provider = match ($request->operation) {
            'inpaint' => addon_setting('ai-image-editor', 'inpaint_provider', 'stability'),
            'outpaint' => addon_setting('ai-image-editor', 'outpaint_provider', 'stability'),
            'bg_remove' => addon_setting('ai-image-editor', 'bg_remove_provider', 'remove_bg'),
            'upscale' => 'replicate',
            'style_transfer' => addon_setting('ai-image-editor', 'style_provider', 'stability'),
            'object_remove' => addon_setting('ai-image-editor', 'object_remove_provider', 'stability'),
            'color_correction' => 'local',
            'text_overlay' => 'local',
            default => 'local',
        };

        $params = array_merge(
            $request->params ?? [],
            $stylePath ? ['style_image_path' => $stylePath] : [],
        );

        $edit = IeEdit::create([
            'ie_session_id' => $session->id,
            'user_id' => auth()->id(),
            'operation' => $request->operation,
            'status' => 'queued',
            'provider' => $provider,
            'input_path' => $session->current_path,
            'mask_path' => $maskPath,
            'params' => $params,
            'credits_deducted' => $credits,
            'version_number' => $session->nextVersionNumber(),
        ]);

        ApplyImageEdit::dispatch($edit->id)->onQueue('media');

        return response()->json([
            'success' => true,
            'edit_id' => $edit->id,
            'edit_ulid' => $edit->ulid,
            'status' => 'queued',
            'version' => $edit->version_number,
        ]);
    }

    public function status(IeEdit $edit): JsonResponse
    {
        if ($edit->user_id !== auth()->id()) {
            abort(403);
        }

        return response()->json([
            'status' => $edit->status,
            'output_url' => $edit->output_url,
            'error' => $edit->error_message,
        ]);
    }

    public function revert(IeEdit $edit): JsonResponse
    {
        if ($edit->user_id !== auth()->id()) {
            abort(403);
        }

        if (! $edit->can_revert_to) {
            return response()->json([
                'success' => false,
                'message' => translate('Cannot revert to this version.'),
            ], 422);
        }

        IeEdit::markAsCurrent($edit);

        return response()->json([
            'success' => true,
            'output_url' => $edit->output_url,
            'version' => $edit->version_number,
        ]);
    }

    public function download(IeEdit $edit)
    {
        if ($edit->user_id !== auth()->id()) {
            abort(403);
        }

        if ($edit->status !== 'completed') {
            abort(422, translate('Edit is not yet completed.'));
        }

        $filename = 'edited-' . $edit->operation . '-' . $edit->ulid . '.png';

        return Storage::disk('public')->download($edit->output_path, $filename);
    }

    public function saveToLibrary(IeEdit $edit): JsonResponse
    {
        if ($edit->user_id !== auth()->id()) {
            abort(403);
        }

        if ($edit->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => translate('Edit is not yet completed.'),
            ], 422);
        }

        app(ApplyImageEdit::class)->saveToLibrary($edit);

        return response()->json([
            'success' => true,
            'message' => translate('Saved to library.'),
        ]);
    }

    private function renderEditor(IeSession $session): Response
    {
        $history = IeEdit::where('ie_session_id', $session->id)
            ->orderBy('version_number')
            ->get([
                'id', 'ulid', 'operation', 'status', 'output_url',
                'version_number', 'is_current', 'created_at', 'error_message',
            ]);

        $service = app(ImageEditorService::class);

        $operationsAvailable = collect([
            'inpaint', 'outpaint', 'bg_remove', 'upscale', 'style_transfer',
            'object_remove', 'color_correction', 'text_overlay',
        ])->mapWithKeys(fn ($op) => [$op => [
            'available' => $service->isProviderConfigured($op),
            'credits' => $service->getCreditsForOperation($op),
        ]])->toArray();

        $maxOutputDimension = (int) addon_setting('ai-image-editor', 'max_output_dimension', 4096);

        return Inertia::render('Addons/ai-image-editor/User/Editor', [
            'session' => $session,
            'history' => $history,
            'operationsAvailable' => $operationsAvailable,
            'maxOutputDimension' => $maxOutputDimension,
        ]);
    }

    private function renderEditorWithNoSession(): Response
    {
        $service = app(ImageEditorService::class);

        $operationsAvailable = collect([
            'inpaint', 'outpaint', 'bg_remove', 'upscale', 'style_transfer',
            'object_remove', 'color_correction', 'text_overlay',
        ])->mapWithKeys(fn ($op) => [$op => [
            'available' => $service->isProviderConfigured($op),
            'credits' => $service->getCreditsForOperation($op),
        ]])->toArray();

        $maxOutputDimension = (int) addon_setting('ai-image-editor', 'max_output_dimension', 4096);

        return Inertia::render('Addons/ai-image-editor/User/Editor', [
            'session' => null,
            'history' => [],
            'operationsAvailable' => $operationsAvailable,
            'maxOutputDimension' => $maxOutputDimension,
        ]);
    }
}
