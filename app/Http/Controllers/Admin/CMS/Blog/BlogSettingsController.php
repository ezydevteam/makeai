<?php

namespace App\Http\Controllers\Admin\CMS\Blog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogSettingsRequest;
use App\Models\Admin;
use App\Services\SocialService;
use Inertia\Inertia;

class BlogSettingsController extends Controller
{
    public function edit()
    {
        $this->authorizeBlog();

        return Inertia::render('Admin/CMS/Blog/Settings', [
            'settings' => [
                'posts_per_page' => (int) settings('blog_posts_per_page', 9),
                'related_posts_count' => (int) settings('blog_related_posts_count', 3),
                'related_posts_algorithm' => settings('blog_related_posts_algorithm', 'tags_first'),
                'auto_excerpt_length' => (int) settings('blog_auto_excerpt_length', 160),
                'default_author' => settings('blog_default_author'),
                'rss_posts_count' => (int) settings('blog_rss_posts_count', 20),
                'words_per_minute' => (int) settings('blog_words_per_minute', 200),
                'blog_sidebar_position' => settings('blog_sidebar_position', 'right'),
                'show_reading_time_archive' => (bool) settings('blog_show_reading_time_archive', true),
                'show_view_count_archive' => (bool) settings('blog_show_view_count_archive', true),
                'sidebar_post_position' => settings('blog_sidebar_post_position', 'right'),
                'post_layout_width' => settings('blog_post_layout_width', 'default'),
                'post_layout_centered' => (bool) settings('blog_post_layout_centered', false),
                'show_reading_time_post' => (bool) settings('blog_show_reading_time_post', true),
                'show_view_count_post' => (bool) settings('blog_show_view_count_post', true),
                'show_published_date_post' => (bool) settings('blog_show_published_date_post', true),
                'show_related_posts_post' => (bool) settings('blog_show_related_posts_post', true),
                'show_comments_post' => (bool) settings('blog_show_comments_post', true),
                'show_post_author_post' => (bool) settings('blog_show_post_author_post', true),
                'show_tags_post' => (bool) settings('blog_show_tags_post', true),
                'post_social_share_position' => settings('blog_post_social_share_position', 'both'),
                'social_share_networks' => settings('social_share_networks', array_keys(SocialService::SHARE_NETWORKS)),
                'social_share_blog_style' => settings('social_share_blog_style', 'icon-label'),
                'social_share_show_counts' => (bool) settings('social_share_show_counts', false),
                'comments_enabled' => (bool) settings('comments_enabled', true),
                'comments_auto_approve_users' => (bool) settings('comments_auto_approve_users', true),
                'comments_allow_guests' => (bool) settings('comments_allow_guests', false),
                'comments_require_approval' => (bool) settings('comments_require_approval', false),
                'comments_notify_admin' => (bool) settings('comments_notify_admin', false),
                'comments_poll_seconds' => (int) settings('comments_poll_seconds', 60),
            ],
            'authors' => Admin::orderBy('name')->get(['id', 'name']),
            'shareNetworks' => collect(SocialService::SHARE_NETWORKS)
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values(),
        ]);
    }

    public function update(BlogSettingsRequest $request)
    {
        foreach ($request->safe()->all() as $key => $value) {
            $type = is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : (is_array($value) ? 'json' : 'string'));
            $settingKey = str_starts_with($key, 'social_share_') || str_starts_with($key, 'blog_')
                ? $key
                : (str_starts_with($key, 'comments_') ? $key : 'blog_'.$key);
            $group = str_starts_with($key, 'social_share_')
                ? 'social'
                : (str_starts_with($key, 'comments_') ? 'comments' : 'blog');

            settings_set($settingKey, $value, $type, $group);
        }

        return back()->with('success', translate('Blog settings saved.'));
    }

    private function authorizeBlog(): void
    {
        if (! auth('admin')->user()?->hasPermission('content.blog')) {
            abort(403, translate('Unauthorized.'));
        }
    }
}
