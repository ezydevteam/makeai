<?php

declare(strict_types=1);

namespace Addons\AiImageEditor\Services;

use Addons\AiImageEditor\Models\IeEdit;
use Addons\AiImageEditor\Services\Providers\ClipdropClient;
use Addons\AiImageEditor\Services\Providers\ImageEditException;
use Addons\AiImageEditor\Services\Providers\RemoveBgClient;
use Addons\AiImageEditor\Services\Providers\ReplicateClient;
use Addons\AiImageEditor\Services\Providers\StabilityClient;

class ImageEditorService
{
    public function __construct(
        private StabilityClient $stability,
        private ReplicateClient $replicate,
        private RemoveBgClient $removeBg,
        private ClipdropClient $clipdrop,
        private GdEditService $gd,
    ) {
    }

    public function apply(IeEdit $edit): string
    {
        return match ($edit->operation) {
            'inpaint' => $this->handleInpaint($edit),
            'outpaint' => $this->handleOutpaint($edit),
            'bg_remove' => $this->handleBgRemove($edit),
            'upscale' => $this->handleUpscale($edit),
            'style_transfer' => $this->handleStyleTransfer($edit),
            'object_remove' => $this->handleObjectRemove($edit),
            'color_correction' => $this->handleColorCorrection($edit),
            'text_overlay' => $this->handleTextOverlay($edit),
            default => throw new ImageEditException("Unknown operation: {$edit->operation}"),
        };
    }

    public function getCreditsForOperation(string $operation): int
    {
        return match ($operation) {
            'inpaint' => (int) addon_setting('ai-image-editor', 'credits_inpaint', 15),
            'outpaint' => (int) addon_setting('ai-image-editor', 'credits_outpaint', 15),
            'bg_remove' => (int) addon_setting('ai-image-editor', 'credits_bg_remove', 5),
            'upscale' => (int) addon_setting('ai-image-editor', 'credits_upscale', 20),
            'style_transfer' => (int) addon_setting('ai-image-editor', 'credits_style_transfer', 20),
            'object_remove' => (int) addon_setting('ai-image-editor', 'credits_object_remove', 15),
            'color_correction' => (int) addon_setting('ai-image-editor', 'credits_color_correction', 0),
            'text_overlay' => (int) addon_setting('ai-image-editor', 'credits_text_overlay', 0),
            default => 0,
        };
    }

    public function buildOutputPath(IeEdit $edit, string $suffix = ''): string
    {
        return 'image-editor/' . $edit->user_id . '/' . $edit->ulid . ($suffix ? "_{$suffix}" : '') . '.png';
    }

    public function isProviderConfigured(string $operation): bool
    {
        return match ($operation) {
            'inpaint' => $this->hasProvider('inpaint'),
            'outpaint' => $this->hasProvider('outpaint'),
            'bg_remove' => $this->hasProvider('bg_remove'),
            'upscale' => $this->hasApiKey('replicate_api_key'),
            'style_transfer' => $this->hasProvider('style'),
            'object_remove' => $this->hasProvider('object_remove'),
            'color_correction', 'text_overlay' => true,
            default => false,
        };
    }

    private function handleInpaint(IeEdit $edit): string
    {
        $provider = addon_setting('ai-image-editor', 'inpaint_provider', 'stability');
        $prompt = $edit->params['prompt'] ?? 'fill region naturally';
        $outputPath = $this->buildOutputPath($edit, 'inpaint');

        return match ($provider) {
            'stability' => $this->stability->inpaint($edit->input_path, (string) $edit->mask_path, $prompt, ['output_path' => $outputPath]),
            'replicate' => $this->replicate->sdInpaint($edit->input_path, (string) $edit->mask_path, $prompt),
            default => throw new ImageEditException("Unknown inpaint provider: {$provider}"),
        };
    }

    private function handleOutpaint(IeEdit $edit): string
    {
        $provider = addon_setting('ai-image-editor', 'outpaint_provider', 'stability');
        $expand = array_intersect_key(
            $edit->params ?? [],
            array_flip(['left', 'right', 'up', 'down']),
        );
        $prompt = $edit->params['prompt'] ?? '';
        $outputPath = $this->buildOutputPath($edit, 'outpaint');

        return match ($provider) {
            'stability' => $this->stability->outpaint($edit->input_path, $expand, $prompt, ['output_path' => $outputPath]),
            default => throw new ImageEditException("Outpainting only available with Stability AI."),
        };
    }

    private function handleBgRemove(IeEdit $edit): string
    {
        $provider = addon_setting('ai-image-editor', 'bg_remove_provider', 'remove_bg');

        return match ($provider) {
            'remove_bg' => $this->removeBg->removeBackground($edit->input_path),
            'clipdrop' => $this->clipdrop->removeBackground($edit->input_path),
            default => throw new ImageEditException("Unknown bg_remove provider: {$provider}"),
        };
    }

    private function handleUpscale(IeEdit $edit): string
    {
        $scale = (int) ($edit->params['scale'] ?? 4);

        return $this->replicate->upscale($edit->input_path, $scale);
    }

    private function handleStyleTransfer(IeEdit $edit): string
    {
        $stylePath = $edit->params['style_image_path'] ?? null;

        if (! $stylePath) {
            throw new ImageEditException('Style image is required for style transfer.');
        }

        return $this->stability->styleTransfer(
            $edit->input_path,
            $stylePath,
            (float) ($edit->params['fidelity'] ?? 0.5),
        );
    }

    private function handleObjectRemove(IeEdit $edit): string
    {
        if (! $edit->mask_path) {
            throw new ImageEditException('Mask is required for object removal.');
        }

        $provider = addon_setting('ai-image-editor', 'object_remove_provider', 'stability');

        return match ($provider) {
            'stability' => $this->stability->objectRemove($edit->input_path, $edit->mask_path),
            'clipdrop' => $this->clipdrop->removeObject($edit->input_path, $edit->mask_path),
            default => throw new ImageEditException("Unknown object_remove provider: {$provider}"),
        };
    }

    private function handleColorCorrection(IeEdit $edit): string
    {
        $outputPath = $this->buildOutputPath($edit, 'cc');

        return $this->gd->colorCorrection($edit->input_path, $edit->params ?? [], $outputPath);
    }

    private function handleTextOverlay(IeEdit $edit): string
    {
        $outputPath = $this->buildOutputPath($edit, 'txt');

        return $this->gd->textOverlay($edit->input_path, $edit->params ?? [], $outputPath);
    }

    private function hasProvider(string $operationKey): bool
    {
        $provider = match ($operationKey) {
            'inpaint' => addon_setting('ai-image-editor', 'inpaint_provider', 'stability'),
            'outpaint' => addon_setting('ai-image-editor', 'outpaint_provider', 'stability'),
            'bg_remove' => addon_setting('ai-image-editor', 'bg_remove_provider', 'remove_bg'),
            'style' => addon_setting('ai-image-editor', 'style_provider', 'stability'),
            'object_remove' => addon_setting('ai-image-editor', 'object_remove_provider', 'stability'),
            default => null,
        };

        if (! $provider) {
            return false;
        }

        $key = match ($provider) {
            'stability' => 'stability_api_key',
            'replicate' => 'replicate_api_key',
            'remove_bg' => 'remove_bg_api_key',
            'clipdrop' => 'clipdrop_api_key',
            default => null,
        };

        return $key ? $this->hasApiKey($key) : false;
    }

    private function hasApiKey(string $key): bool
    {
        return ! empty(addon_setting('ai-image-editor', $key));
    }
}
