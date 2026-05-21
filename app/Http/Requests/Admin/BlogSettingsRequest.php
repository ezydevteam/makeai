<?php

namespace App\Http\Requests\Admin;

use App\Services\SocialService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlogSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && auth('admin')->user()->hasPermission('content.blog');
    }

    public function rules(): array
    {
        return [
            'posts_per_page' => ['required', 'integer', 'min:1', 'max:48'],
            'related_posts_count' => ['required', 'integer', 'min:1', 'max:12'],
            'related_posts_algorithm' => ['required', Rule::in(['tags_first', 'category_first', 'recent'])],
            'auto_excerpt_length' => ['required', 'integer', 'min:80', 'max:500'],
            'default_author' => ['nullable', 'integer', 'exists:admins,id'],
            'default_allow_comments' => ['required', 'boolean'],
            'rss_posts_count' => ['required', 'integer', 'min:1', 'max:100'],
            'show_reading_time' => ['required', 'boolean'],
            'words_per_minute' => ['required', 'integer', 'min:100', 'max:500'],
            'blog_sidebar' => ['required', 'boolean'],
            'blog_sidebar_position' => ['required', Rule::in(['right', 'left'])],
            'social_share_networks' => ['required', 'array', 'min:1'],
            'social_share_networks.*' => ['required', 'string', Rule::in(array_keys(SocialService::SHARE_NETWORKS))],
            'social_share_blog_style' => ['required', 'string', Rule::in(SocialService::SHARE_STYLES)],
            'social_share_show_counts' => ['required', 'boolean'],
        ];
    }
}
