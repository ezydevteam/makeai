<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class NewsletterCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('content')) {
            $this->merge([
                'content' => \App\Services\TiptapHtmlSanitizer::sanitize(
                    (string) $this->input('content'),
                    \App\Services\TiptapHtmlSanitizer::FULL_TAGS
                ),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'audience' => ['required', 'string', 'in:subscribers,users_all,users_active,users_inactive,users_pro,users_free'],
        ];
    }
}
