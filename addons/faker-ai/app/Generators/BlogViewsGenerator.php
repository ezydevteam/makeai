<?php

namespace Addons\FakerAi\Generators;

use Addons\FakerAi\Models\FakerBatch;
use App\Models\BlogPost;
use App\Models\BlogPostView;

/**
 * Fake blog views. Inserts blog_post_views rows (what analytics reads) AND bumps the post's
 * views_count (what the blog UI shows). No AI. Rollback deletes the rows and subtracts the
 * counter back down.
 */
class BlogViewsGenerator extends AbstractGenerator
{
    public function type(): string
    {
        return 'blog-views';
    }

    public function label(): string
    {
        return 'Blog Views';
    }

    public function group(): string
    {
        return 'Blog';
    }

    public function usesAi(): bool
    {
        return false;
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

        $shares = $this->distribute($batch->requested_count, $posts->count());
        $applied = 0;

        foreach ($posts as $i => $post) {
            $views = $shares[$i];
            if ($views <= 0) {
                continue;
            }

            for ($v = 0; $v < $views; $v++) {
                $row = BlogPostView::create([
                    'post_id' => $post->id,
                    'ip_address' => $this->randomIp(),
                    'user_id' => null,
                    'viewed_at' => $this->backdate(),
                ]);
                $batch->recordInsert($row);
            }

            BlogPost::whereKey($post->id)->increment('views_count', $views);
            $batch->recordIncrement($post, 'views_count', $views);
            $applied += $views;
        }

        return $applied;
    }

    private function distribute(int $total, int $buckets): array
    {
        if ($buckets <= 0) {
            return [];
        }

        $base = intdiv($total, $buckets);
        $remainder = $total % $buckets;

        $shares = [];
        for ($i = 0; $i < $buckets; $i++) {
            $shares[$i] = $base + ($i < $remainder ? 1 : 0);
        }

        return $shares;
    }

    private function resolvePosts(FakerBatch $batch)
    {
        return $this->applyTargets(BlogPost::published(), $batch, 'ulid')
            ->get(['id', 'ulid'])
            ->values();
    }
}
