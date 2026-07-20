<?php

namespace Addons\FakerAi\Generators;

use Addons\FakerAi\Models\FakerBatch;
use App\Models\BlogPost;
use App\Models\Comment;
use Illuminate\Support\Str;

/**
 * Fake blog comments, stored as approved GUEST comments (guest_name/guest_email, no user row)
 * so a comments run never creates member accounts. Rollback force-deletes them past the soft
 * delete.
 */
class BlogCommentsGenerator extends AbstractGenerator
{
    public function type(): string
    {
        return 'blog-comments';
    }

    public function label(): string
    {
        return 'Blog Comments';
    }

    public function group(): string
    {
        return 'Blog';
    }

    public function requiresTarget(): bool
    {
        return true;
    }

    public function targets(): array
    {
        $posts = BlogPost::published()
            ->latest('published_at')
            ->limit(200)
            ->get(['ulid', 'title'])
            ->map(fn (BlogPost $p) => ['value' => $p->ulid, 'label' => $p->title])
            ->all();

        return array_merge([['value' => '*', 'label' => 'Spread across all published posts']], $posts);
    }

    public function generate(FakerBatch $batch): int
    {
        $posts = $this->resolvePosts($batch);
        if ($posts->isEmpty()) {
            return 0;
        }

        $items = $this->content->generate(
            noun: 'blog comment',
            fields: [
                'author_name' => 'the commenter\'s full name',
                'comment' => 'a 1-2 sentence reader comment reacting to a blog article, varied in tone',
            ],
            count: $batch->requested_count,
            style: $this->style($batch),
            batch: $batch,
        );

        $created = 0;
        foreach ($items as $i => $item) {
            $content = trim((string) ($item['comment'] ?? ''));
            if ($content === '') {
                continue;
            }

            $post = $posts[$i % $posts->count()];
            $name = trim((string) ($item['author_name'] ?? '')) ?: 'Reader '.Str::upper(Str::random(4));
            $date = $this->backdate();

            $comment = new Comment([
                'commentable_type' => BlogPost::class,
                'commentable_id' => $post->id,
                'user_id' => null,
                'content' => $content,
                'status' => 'approved',
                'guest_name' => mb_substr($name, 0, 120),
                'guest_email' => Str::slug($name, '.').'.'.Str::lower(Str::random(5)).'@faker.local',
                'likes_count' => random_int(0, 15),
            ]);
            $comment->created_at = $date;
            $comment->updated_at = $date;
            $comment->save();

            $batch->recordInsert($comment);
            $created++;
        }

        return $created;
    }

    private function resolvePosts(FakerBatch $batch)
    {
        return $this->applyTargets(BlogPost::published(), $batch, 'ulid')
            ->get(['id', 'ulid'])
            ->values();
    }
}
