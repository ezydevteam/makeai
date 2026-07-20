<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogPostAiAssistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->hasPermission('content.blog');
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in([
                'generate_title',
                'generate_excerpt',
                'generate_seo',
                'generate_tags',
                'improve_paragraph',
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
            'title' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'selected_text' => ['nullable', 'string', 'max:8000'],
        ];
    }
}
