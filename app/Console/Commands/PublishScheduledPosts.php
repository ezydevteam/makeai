<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use Illuminate\Console\Command;

class PublishScheduledPosts extends Command
{
    protected $signature = 'blog:publish-scheduled';

    protected $description = 'Publish all blog posts whose scheduled time has arrived.';

    public function handle(): int
    {
        $posts = BlogPost::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($posts->isEmpty()) {
            $this->info('No scheduled posts to publish.');

            return self::SUCCESS;
        }

        foreach ($posts as $post) {
            $post->update([
                'status' => 'published',
                'published_at' => $post->scheduled_at,
            ]);

            $this->line("Published: <info>{$post->title}</info>");
        }

        $this->info(count($posts).' post(s) published.');

        return self::SUCCESS;
    }
}
