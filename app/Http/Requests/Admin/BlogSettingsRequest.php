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
            'rss_posts_count' => ['required', 'integer', 'min:1', 'max:100'],
            'words_per_minute' => ['required', 'integer', 'min:100', 'max:500'],
            'blog_sidebar_position' => ['required', Rule::in(['right', 'left', 'none'])],
            'show_reading_time_archive' => ['required', 'boolean'],
            'show_view_count_archive' => ['required', 'boolean'],
            'sidebar_post_position' => ['required', Rule::in(['right', 'left', 'none'])],
            'post_layout_width' => ['required', Rule::in(['default', 'boxed'])],
            'post_layout_centered' => ['required', 'boolean'],
            'show_reading_time_post' => ['required', 'boolean'],
            'show_view_count_post' => ['required', 'boolean'],
            'show_published_date_post' => ['required', 'boolean'],
            'show_related_posts_post' => ['required', 'boolean'],
            'show_comments_post' => ['required', 'boolean'],
            'show_post_author_post' => ['required', 'boolean'],
            'show_tags_post' => ['required', 'boolean'],
            'post_social_share_position' => ['required', Rule::in(['hide', 'top', 'bottom', 'both'])],
            'social_share_networks' => ['required', 'array', 'min:1'],
            'social_share_networks.*' => ['required', 'string', Rule::in(array_keys(SocialService::SHARE_NETWORKS))],
            'social_share_blog_style' => ['required', 'string', Rule::in(SocialService::SHARE_STYLES)],
            'social_share_show_counts' => ['required', 'boolean'],
            'comments_enabled' => ['required', 'boolean'],
            'comments_auto_approve_users' => ['required', 'boolean'],
            'comments_allow_guests' => ['required', 'boolean'],
            'comments_require_approval' => ['required', 'boolean'],
            'comments_notify_admin' => ['required', 'boolean'],
            'comments_poll_seconds' => ['required', 'integer', 'min:10', 'max:300'],
        ];
    }
}
