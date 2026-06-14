<?php

declare(strict_types=1);

namespace Addons\AiImageEditor\Http\Controllers\Admin;

use Addons\AiImageEditor\Http\Requests\ImageEditorSettingsRequest;
use Addons\AiImageEditor\Services\ImageEditorService;
use Inertia\Inertia;
use Inertia\Response;

class ImageEditorSettingsController extends \App\Http\Controllers\Controller
{
    public function edit(): Response
    {
        $service = app(ImageEditorService::class);

        $operationsStatus = collect([
            'inpaint', 'outpaint', 'bg_remove', 'upscale', 'style_transfer', 'object_remove',
        ])->mapWithKeys(fn ($op) => [$op => $service->isProviderConfigured($op)])->toArray();

        $settings = [
            'enabled' => (bool) addon_setting('ai-image-editor', 'enabled', true),
            'inpaint_provider' => addon_setting('ai-image-editor', 'inpaint_provider', 'stability'),
            'outpaint_provider' => addon_setting('ai-image-editor', 'outpaint_provider', 'stability'),
            'bg_remove_provider' => addon_setting('ai-image-editor', 'bg_remove_provider', 'remove_bg'),
            'upscale_provider' => addon_setting('ai-image-editor', 'upscale_provider', 'replicate'),
            'style_provider' => addon_setting('ai-image-editor', 'style_provider', 'stability'),
            'object_remove_provider' => addon_setting('ai-image-editor', 'object_remove_provider', 'stability'),
            'stability_api_key' => addon_setting('ai-image-editor', 'stability_api_key'),
            'replicate_api_key' => addon_setting('ai-image-editor', 'replicate_api_key'),
            'remove_bg_api_key' => addon_setting('ai-image-editor', 'remove_bg_api_key'),
            'clipdrop_api_key' => addon_setting('ai-image-editor', 'clipdrop_api_key'),
            'credits_inpaint' => (int) addon_setting('ai-image-editor', 'credits_inpaint', 15),
            'credits_outpaint' => (int) addon_setting('ai-image-editor', 'credits_outpaint', 15),
            'credits_bg_remove' => (int) addon_setting('ai-image-editor', 'credits_bg_remove', 5),
            'credits_upscale' => (int) addon_setting('ai-image-editor', 'credits_upscale', 20),
            'credits_style_transfer' => (int) addon_setting('ai-image-editor', 'credits_style_transfer', 20),
            'credits_object_remove' => (int) addon_setting('ai-image-editor', 'credits_object_remove', 15),
            'credits_color_correction' => (int) addon_setting('ai-image-editor', 'credits_color_correction', 0),
            'credits_text_overlay' => (int) addon_setting('ai-image-editor', 'credits_text_overlay', 0),
            'max_input_size_mb' => (int) addon_setting('ai-image-editor', 'max_input_size_mb', 10),
            'max_output_dimension' => (int) addon_setting('ai-image-editor', 'max_output_dimension', 4096),
            'history_limit_per_image' => (int) addon_setting('ai-image-editor', 'history_limit_per_image', 20),
            'auto_save_to_library' => (bool) addon_setting('ai-image-editor', 'auto_save_to_library', true),
        ];

        return Inertia::render('Addons/ai-image-editor/Admin/Settings', [
            'settings' => $settings,
            'operationsStatus' => $operationsStatus,
        ]);
    }

    public function update(ImageEditorSettingsRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            $type = $this->getSettingType($key);
            addon_setting_set('ai-image-editor', $key, $value, $type);
        }

        return redirect()->route('addon.ie.admin.settings')
            ->with('flash', translate('Settings saved.'));
    }

    private function getSettingType(string $key): string
    {
        return match ($key) {
            'enabled', 'auto_save_to_library' => 'boolean',
            'credits_inpaint', 'credits_outpaint', 'credits_bg_remove',
            'credits_upscale', 'credits_style_transfer', 'credits_object_remove',
            'credits_color_correction', 'credits_text_overlay',
            'max_input_size_mb', 'max_output_dimension', 'history_limit_per_image' => 'integer',
            'stability_api_key', 'replicate_api_key', 'remove_bg_api_key', 'clipdrop_api_key' => 'encrypted',
            default => 'string',
        };
    }
}
