<?php

namespace Addons\FakerAi\Generators;

use Addons\FakerAi\Models\FakerBatch;
use App\Models\BlogPost;

/**
 * Fake social-share counts on blog posts. Bumps the share_count column (added by this addon's
 * migration). No AI, no rows — just a counter, reversed exactly on rollback.
 */
class BlogSharesGenerator extends AbstractGenerator
{
    public function type(): string
    {
        return 'blog-shares';
    }

    public function label(): string
    {
        return 'Blog Share Counts';
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
            $amount = $shares[$i];
            if ($amount <= 0) {
                continue;
            }

            BlogPost::whereKey($post->id)->increment('share_count', $amount);
            $batch->recordIncrement($post, 'share_count', $amount);
            $applied += $amount;
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
