<?php

namespace Addons\AiKnowledgeBase\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class KbCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:100', "unique:kb_categories,slug,{$categoryId}"],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:160'],
            'meta_desc' => ['nullable', 'string', 'max:320'],
        ];
    }
}
