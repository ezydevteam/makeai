<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->hasPermission('content.blog');
    }

    public function rules(): array
    {
        $postId = $this->route('post')?->id;

        return [
            'title' => ['required', 'string', 'max:500'],
            'slug' => ['nullable', 'string', 'max:500', Rule::unique('blog_posts', 'slug')->ignore($postId)],
            'excerpt' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'featured_image' => ['nullable', 'string', 'max:500'],
            'featured_image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'featured_image_alt' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published', 'scheduled', 'private'])],
            'published_at' => ['nullable', 'date'],
            'scheduled_at' => ['nullable', 'date', 'required_if:status,scheduled'],
            'author_id' => ['required', 'integer', 'exists:admins,id'],
            'is_featured' => ['required', 'boolean'],
            'is_sticky' => ['required', 'boolean'],
            'allow_comments' => ['required', 'boolean'],
            'reading_time' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string'],
            'og_image' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url', 'max:500'],
            'schema_type' => ['required', Rule::in(['Article', 'BlogPosting', 'NewsArticle'])],
            'no_index' => ['required', 'boolean'],
            'show_author' => ['required', 'boolean'],
            'show_date' => ['required', 'boolean'],
            'show_reading_time' => ['required', 'boolean'],
            'show_share_buttons' => ['required', 'boolean'],
            'show_related_posts' => ['required', 'boolean'],
            'show_toc' => ['required', 'boolean'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:blog_categories,id'],
            'tags' => ['array'],
            'tags.*' => ['string', 'max:80'],
        ];
    }
}
