<?php

namespace App\Observers;

use App\Models\BlogPost;

class BlogPostObserver
{
    public function deleted(BlogPost $post): void
    {
        if ($post->isForceDeleting()) {
            return;
        }

        $post->comments()->delete();
    }

    public function restored(BlogPost $post): void
    {
        $post->comments()->onlyTrashed()->restore();
    }
}
