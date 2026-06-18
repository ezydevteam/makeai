<?php

namespace App\Observers;

use App\Models\BlogPost;

class BlogPostObserver
{
    public function deleted(BlogPost $post): void
    {
        // Soft delete: also soft delete related comments
        $post->comments()->delete();
    }

    public function restoring(BlogPost $post): void
    {
        // Restore soft-deleted comments when post is restored
        $post->comments()->onlyTrashed()->restore();
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
