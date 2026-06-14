<?php

declare(strict_types=1);

namespace Addons\AiImageEditor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImageEditRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'operation' => ['required', 'string', 'in:inpaint,outpaint,bg_remove,upscale,style_transfer,object_remove,color_correction,text_overlay'],
            'params' => ['nullable', 'array'],
            'params.prompt' => [
                'required_if:operation,inpaint',
                'nullable',
                'string',
                'max:1000',
            ],
            'params.scale' => ['nullable', 'integer', 'in:2,4'],
            'params.left' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'params.right' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'params.up' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'params.down' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'params.fidelity' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'params.brightness' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'params.contrast' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'params.saturation' => ['nullable', 'numeric', 'min:-100', 'max:100'],
            'params.hue' => ['nullable', 'numeric', 'min:0', 'max:360'],
            'params.sharpness' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'params.text' => ['required_if:operation,text_overlay', 'nullable', 'string', 'max:500'],
            'params.font_size' => ['nullable', 'integer', 'min:8', 'max:200'],
            'params.font_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'params.x' => ['nullable', 'integer', 'min:0'],
            'params.y' => ['nullable', 'integer', 'min:0'],
            'mask' => ['nullable', 'file', 'mimes:png', 'max:5120'],
            'style_image' => ['required_if:operation,style_transfer', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'params.prompt.required_if' => translate('A prompt is required for inpainting.'),
            'params.text.required_if' => translate('Text is required for text overlay.'),
            'style_image.required_if' => translate('A style image is required for style transfer.'),
        ];
    }
}
