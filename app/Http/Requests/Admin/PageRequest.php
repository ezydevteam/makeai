<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->hasPermission('content.pages');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => $this->input('parent_id') ?: null,
            'published_at' => $this->input('published_at') ?: null,
            'sort_order' => $this->input('sort_order', 0),
            'show_title' => $this->boolean('show_title'),
            'show_breadcrumbs' => $this->boolean('show_breadcrumbs'),
            'show_featured_image' => $this->boolean('show_featured_image'),
            'show_sidebar' => $this->boolean('show_sidebar'),
        ]);
    }

    public function rules(): array
    {
        $pageId = $this->route('page')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('pages', 'slug')->ignore($pageId)],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'template' => ['required', Rule::in(['default', 'full_width', 'blank', 'landing'])],
            'featured_image' => ['nullable', 'image', 'max:2048'],
            'og_image' => ['nullable', 'image', 'max:2048'],
            'status' => ['required', Rule::in(['draft', 'published', 'scheduled'])],
            'published_at' => ['nullable', 'date', 'required_if:status,scheduled'],
            'password' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:pages,id', Rule::notIn([$pageId])],
            'sort_order' => ['nullable', 'integer'],
            'show_title' => ['required', 'boolean'],
            'show_breadcrumbs' => ['required', 'boolean'],
            'show_featured_image' => ['required', 'boolean'],
            'show_sidebar' => ['required', 'boolean'],
            'sidebar_position' => ['required', Rule::in(['left', 'right'])],
            'container_width' => ['required', Rule::in(['default', 'wide', 'full', 'narrow'])],
        ];
    }
}
