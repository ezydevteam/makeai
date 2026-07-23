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
            // The selection actions are the ones RichEditor offers on its own
            // whenever text is highlighted — the editor swaps its menu to that
            // built-in list, so rejecting them here made every highlighted-text
            // assist fail with a validation error.
            'action' => ['required', Rule::in([
                'generate_content',
                'improve_content',
                'generate_subject',
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
            'subject' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'selected_text' => ['nullable', 'string', 'max:8000'],
        ];
    }
}
