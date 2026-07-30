<?php

declare(strict_types=1);

namespace Addons\AiImagePro\Http\Requests;

use Addons\AiImagePro\Services\ImageAccessService;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Uploading a source image is not an operation — it produces no job and costs
 * no credits — so it gates on Studio access rather than on the registry.
 * The gate lives in authorize() so it runs *before* validation: a guest who is
 * not allowed in gets 401, not a 422 about their file.
 */
class UploadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $access = app(ImageAccessService::class);
        $user = $this->user();

        if (! $access->addonEnabled()) {
            $this->deny(translate('This feature is currently unavailable.'), 403);
        }

        if (! $access->canAccessStudio($user instanceof User ? $user : null)) {
            $this->deny(
                $user
                    ? translate('You do not have access to the Studio.')
                    : translate('Please sign in to use this feature.'),
                $user ? 403 : 401,
            );
        }

        return true;
    }

    /**
     * Accept the file under either `image` or `file` so a client that posts a
     * plain FormData("file", …) is not silently rejected.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->hasFile('image') && $this->hasFile('file')) {
            $this->files->set('image', $this->file('file'));
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $maxKb = max(1, (int) addon_setting('ai-image-pro', 'max_input_size_mb', 10)) * 1024;

        return [
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:' . $maxKb],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => translate('Please choose an image to upload.'),
            'image.mimes' => translate('Only JPG, PNG and WebP images are supported.'),
            'image.max' => translate('This image is larger than the allowed upload size.'),
        ];
    }

    private function deny(string $message, int $status): never
    {
        throw new HttpResponseException(
            response()->json(['success' => false, 'message' => $message], $status)
        );
    }
}
