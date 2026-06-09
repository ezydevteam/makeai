<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MailTemplateStoreRequest extends FormRequest
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
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('mail_templates', 'slug')],
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
