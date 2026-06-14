<?php

declare(strict_types=1);

namespace Addons\AiImageEditor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImageEditorSettingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => $this->boolean('enabled', false),
            'auto_save_to_library' => $this->boolean('auto_save_to_library', false),
        ]);
    }

    public function rules(): array
    {
        return [
            'enabled' => ['boolean'],
            'inpaint_provider' => ['required', 'string', 'in:stability,replicate'],
            'outpaint_provider' => ['required', 'string', 'in:stability,replicate'],
            'bg_remove_provider' => ['required', 'string', 'in:remove_bg,clipdrop'],
            'upscale_provider' => ['required', 'string', 'in:replicate'],
            'style_provider' => ['required', 'string', 'in:stability,replicate'],
            'object_remove_provider' => ['required', 'string', 'in:stability,clipdrop'],
            'stability_api_key' => ['nullable', 'string', 'max:500'],
            'replicate_api_key' => ['nullable', 'string', 'max:500'],
            'remove_bg_api_key' => ['nullable', 'string', 'max:500'],
            'clipdrop_api_key' => ['nullable', 'string', 'max:500'],
            'credits_inpaint' => ['required', 'integer', 'min:0'],
            'credits_outpaint' => ['required', 'integer', 'min:0'],
            'credits_bg_remove' => ['required', 'integer', 'min:0'],
            'credits_upscale' => ['required', 'integer', 'min:0'],
            'credits_style_transfer' => ['required', 'integer', 'min:0'],
            'credits_object_remove' => ['required', 'integer', 'min:0'],
            'credits_color_correction' => ['required', 'integer', 'min:0'],
            'credits_text_overlay' => ['required', 'integer', 'min:0'],
            'max_input_size_mb' => ['required', 'integer', 'min:1', 'max:100'],
            'max_output_dimension' => ['required', 'integer', 'min:256', 'max:16384'],
            'history_limit_per_image' => ['required', 'integer', 'min:1', 'max:100'],
            'auto_save_to_library' => ['boolean'],
        ];
    }
}
