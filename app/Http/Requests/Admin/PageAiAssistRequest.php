<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageAiAssistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in([
                'generate_title',
                'generate_content',
                'generate_excerpt',
                'generate_seo',
                'improve_selection',
                'shorten_selection',
                'expand_selection',
                'rephrase_selection',
                'translate_selection',
                'change_tone',
                'summarize_selection',
                'fix_grammar',
                'continue_writing',
            ])],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'selected_text' => ['nullable', 'string', 'max:8000'],
        ];
    }
}
