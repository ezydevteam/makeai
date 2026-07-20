<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MailTemplateAiAssistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in([
                'generate_content',
                'improve_content',
                'generate_subject',
            ])],
            'subject' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'selected_text' => ['nullable', 'string', 'max:8000'],
        ];
    }
}
