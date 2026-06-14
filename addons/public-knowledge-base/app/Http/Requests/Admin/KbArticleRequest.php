<?php

namespace Addons\PublicKnowledgeBase\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class KbArticleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'kb_category_id' => ['nullable', 'exists:kb_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'status' => ['in:draft,published'],
            'sort_order' => ['integer', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_desc' => ['nullable', 'string', 'max:320'],
        ];
    }
}
