<?php

namespace App\Observers;

use App\Models\BlogPost;
use App\Services\SitemapService;

class BlogPostObserver
{
    public function saved(BlogPost $post): void
    {
        // Invalidate the cached sitemap when a post's public presence changes
        // (publish/unpublish/schedule, slug change) or a new post is created.
        if ($post->wasRecentlyCreated || $post->wasChanged([
            'status', 'slug', 'published_at', 'scheduled_at',
        ])) {
            app(SitemapService::class)->invalidate();
        }
    }

    public function deleted(BlogPost $post): void
    {
        // Soft delete: also soft delete related comments
        $post->comments()->delete();

        app(SitemapService::class)->invalidate();
    }

    public function restoring(BlogPost $post): void
    {
        // Restore soft-deleted comments when post is restored
        $post->comments()->onlyTrashed()->restore();
    }

    public function restored(BlogPost $post): void
    {
        app(SitemapService::class)->invalidate();
    }

    public function forceDeleting(BlogPost $post): void
    {
        // Permanently delete all related records to prevent database orphaning
        $post->comments()->forceDelete();
        $post->views()->delete(); // BlogPostView has no soft deletes
        $post->revisions()->delete(); // BlogPostRevision has no soft deletes
        $post->favorites()->delete(); // MorphMany favorites
    }
}
