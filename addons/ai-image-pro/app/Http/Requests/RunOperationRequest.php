<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Http\Requests;

use Addons\AiImagePro\Services\ImageAccessService;
use Addons\AiImagePro\Services\OperationRegistry;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Queued operations: `POST /ai-image/ops/{operation}`.
 *
 * Nothing about the operation is hardcoded here. The route parameter is
 * validated against OperationRegistry::keys(), and which fields are *required*
 * (image / mask / prompt / reference) is derived from `$registry->inputs()`.
 * Adding an operation to the registry automatically validates correctly here.
 */
class RunOperationRequest extends FormRequest
{
    public function operation(): string
    {
        return (string) $this->route('operation');
    }

    /**
     * @return array<int, string>
     */
    public function operationInputs(): array
    {
        return app(OperationRegistry::class)->inputs($this->operation());
    }

    public function authorize(): bool
    {
        $registry = app(OperationRegistry::class);
        $operation = $this->operation();

        if ($registry->get($operation) === null) {
            $this->deny(translate('Unknown image operation.'), 404);
        }

        // Local GD operations are answered in-request by ToolController — they
        // never produce a job, so they must not be queued through this endpoint.
        if ($registry->tier($operation) === OperationRegistry::TIER_LOCAL) {
            $this->deny(translate('This operation runs instantly and must be sent to the tools endpoint.'), 422);
        }

        $user = $this->user();

        app(ImageAccessService::class)->assertCanRun(
            $operation,
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
        $inputs = $this->operationInputs();
        $maxKb = max(1, (int) addon_setting('ai-image-pro', 'max_input_size_mb', 10)) * 1024;
        $maxBatch = max(1, (int) addon_setting('ai-image-pro', 'max_batch_size', 4));

        $needsImage = in_array('image', $inputs, true);
        $needsMask = in_array('mask', $inputs, true);
        $needsPrompt = in_array('prompt', $inputs, true);
        $needsReference = in_array('reference', $inputs, true);

        return [
            'asset_ulid' => [$needsImage ? 'required' : 'nullable', 'string', 'size:26'],

            'params' => ['nullable', 'array'],
            'params.prompt' => [$needsPrompt ? 'required' : 'nullable', 'string', 'max:2000'],
            'params.negative_prompt' => ['nullable', 'string', 'max:1000'],
            'params.seed' => ['nullable', 'string', 'max:40'],
            'params.scale' => ['nullable', 'integer', 'min:2', 'max:4'],
            'params.left' => ['nullable', 'integer', 'min:0', 'max:2048'],
            'params.right' => ['nullable', 'integer', 'min:0', 'max:2048'],
            'params.up' => ['nullable', 'integer', 'min:0', 'max:2048'],
            'params.down' => ['nullable', 'integer', 'min:0', 'max:2048'],
            'params.fidelity' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'params.strength' => ['nullable', 'numeric', 'min:0', 'max:1'],

            'model' => ['nullable', 'string', 'max:100'],
            'count' => ['nullable', 'integer', 'min:1', 'max:' . $maxBatch],

            'mask' => [$needsMask ? 'required' : 'nullable', 'file', 'mimes:png', 'max:' . $maxKb],

            'reference' => [
                $needsReference ? 'required_without:reference_asset_ulid' : 'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:' . $maxKb,
            ],
            'reference_asset_ulid' => [
                $needsReference ? 'required_without:reference' : 'nullable',
                'string',
                'size:26',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asset_ulid.required' => translate('Select an image first.'),
            'params.prompt.required' => translate('A prompt is required for this operation.'),
            'mask.required' => translate('Paint a mask over the area you want to change.'),
            'mask.mimes' => translate('The mask must be a PNG image.'),
            'reference.required_without' => translate('A reference image is required for this operation.'),
            'reference_asset_ulid.required_without' => translate('A reference image is required for this operation.'),
        ];
    }

    private function deny(string $message, int $status): never
    {
        throw new HttpResponseException(
            response()->json(['success' => false, 'message' => $message], $status)
        );
    }
}
