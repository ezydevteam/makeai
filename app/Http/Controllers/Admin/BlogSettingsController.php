<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogSettingsRequest;
use App\Models\Admin;
use Inertia\Inertia;

class BlogSettingsController extends Controller
{
    public function edit()
    {
        $this->authorizeBlog();

        return Inertia::render('Admin/Blog/Settings', [
            'settings' => [
                'posts_per_page' => (int) settings('blog_posts_per_page', 9),
                'related_posts_count' => (int) settings('blog_related_posts_count', 3),
                'related_posts_algorithm' => settings('blog_related_posts_algorithm', 'tags_first'),
                'auto_excerpt_length' => (int) settings('blog_auto_excerpt_length', 160),
                'default_author' => settings('blog_default_author'),
                'default_allow_comments' => (bool) settings('blog_default_allow_comments', true),
                'rss_posts_count' => (int) settings('blog_rss_posts_count', 20),
                'show_reading_time' => (bool) settings('blog_show_reading_time', true),
                'words_per_minute' => (int) settings('blog_words_per_minute', 200),
                'blog_sidebar' => (bool) settings('blog_sidebar', true),
                'blog_sidebar_position' => settings('blog_sidebar_position', 'right'),
            ],
            'authors' => Admin::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(BlogSettingsRequest $request)
    {
        foreach ($request->validated() as $key => $value) {
            $type = is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : 'string');
            settings_set('blog_'.$key, $value, $type, 'blog');
        }

        return back()->with('success', translate('Blog settings saved.'));
    }

    private function authorizeBlog(): void
    {
        abort_unless(auth('admin')->user()?->hasPermission('content.blog'), 403);
    }
}
