<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Http\Requests;

use Addons\AiImagePro\Services\ImageAccessService;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Text-to-image. The `generate` key is not a magic string: it is the registry
 * entry this endpoint exists to serve, and every gate below reads its access
 * level, credits and availability from there.
 */
class GenerateImageRequest extends FormRequest
{
    public const OPERATION = 'generate';

    /**
     * Gate before validation so an unauthorised caller gets the access error,
     * not a field error. assertCanRun() throws its own JSON HttpResponseException.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        app(ImageAccessService::class)->assertCanRun(
            self::OPERATION,
            $user instanceof User ? $user : null,
            $this->ip(),
        );

        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $maxBatch = max(1, (int) addon_setting('ai-image-pro', 'max_batch_size', 4));

        return [
            'prompt' => ['required', 'string', 'min:2', 'max:2000'],
            'negative_prompt' => ['nullable', 'string', 'max:1000'],
            'model' => ['nullable', 'string', 'max:100'],
            'aspect' => ['nullable', 'string', 'max:20'],
            'count' => ['nullable', 'integer', 'min:1', 'max:' . $maxBatch],
            'seed' => ['nullable', 'string', 'max:40'],
            'preset' => ['nullable', 'string', 'exists:aip_presets,slug'],
            'reference_asset_ulid' => ['nullable', 'string', 'size:26'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prompt.required' => translate('Describe the image you want to create.'),
            'preset.exists' => translate('That style preset is no longer available.'),
        ];
    }
}
