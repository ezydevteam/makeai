<?php

namespace App\Services;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostRevision;
use App\Models\BlogPostView;
use App\Models\BlogTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlogService
{
    public function publishedQuery(): Builder
    {
        return BlogPost::query()
            ->published()
            ->with(['author:id,name,avatar', 'categories:id,name,slug,color', 'tags:id,name,slug'])
            ->withCount(['comments as comments_count' => fn ($query) => $query->approved()]);
    }

    public function readingTime(string $content): int
    {
        $wordsPerMinute = max((int) settings('blog_words_per_minute', 200), 1);
        $text = strip_tags($content);
        $words = preg_match_all('/\p{L}+[\p{L}\p{Mn}\']*/u', $text);

        return max((int) ceil($words / $wordsPerMinute), 1);
    }

    public function excerpt(string $content): string
    {
        $length = max((int) settings('blog_auto_excerpt_length', 160), 40);

        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($content))), $length);
    }

    public function normalizePostData(array $data, ?int $postId = null): array
    {
        $slug = Str::slug($data['slug'] ?: $data['title']);
        $data['slug'] = $this->uniqueSlug($slug, $postId);
        $data['reading_time'] = (int) ($data['reading_time'] ?: $this->readingTime($data['content']));

        if (empty($data['excerpt']) && in_array($data['status'], ['published', 'scheduled'], true)) {
            $data['excerpt'] = $this->excerpt($data['content']);
        }

        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        if ($data['status'] !== 'scheduled') {
            $data['scheduled_at'] = null;
        }

        return $data;
    }

    public function syncRelations(BlogPost $post, array $categoryIds, array $tagNames): void
    {
        $post->categories()->sync($categoryIds);

        $tagIds = collect($tagNames)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => Str::lower($name))
            ->map(function (string $name) {
                return BlogTag::firstOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name]
                )->id;
            })
            ->values()
            ->all();

        $post->tags()->sync($tagIds);
        $this->refreshCounters();
        $this->forgetRelatedCache($post);
    }

    public function saveRevision(BlogPost $post, ?int $adminId): void
    {
        BlogPostRevision::create([
            'post_id' => $post->id,
            'admin_id' => $adminId,
            'title' => $post->title,
            'content' => $post->content,
            'payload' => $post->only([
                'excerpt',
                'status',
                'published_at',
                'scheduled_at',
                'meta_title',
                'meta_description',
            ]),
        ]);

        $oldRevisionIds = BlogPostRevision::where('post_id', $post->id)
            ->latest()
            ->pluck('id')
            ->slice(50)
            ->values();

        if ($oldRevisionIds->isNotEmpty()) {
            BlogPostRevision::whereIn('id', $oldRevisionIds)->delete();
        }
    }

    public function trackView(BlogPost $post, ?int $userId, string $ipAddress): void
    {
        $cacheKey = 'blog:viewed:'.$post->id.':'.sha1($ipAddress.'|'.($userId ?: 'guest'));

        if (! Cache::add($cacheKey, true, now()->addHours(12))) {
            return;
        }

        DB::transaction(function () use ($post, $userId, $ipAddress) {
            BlogPostView::create([
                'post_id' => $post->id,
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'viewed_at' => now(),
            ]);

            $post->increment('views_count');
        });
    }

    public function relatedPosts(BlogPost $post): Collection
    {
        $algorithm = settings('blog_related_posts_algorithm', 'tags_first');
        $count = max((int) settings('blog_related_posts_count', 3), 1);

        $cacheKey = "blog:related:v2:{$post->id}";

        $cached = Cache::get($cacheKey);

        if (is_array($cached) && isset($cached['ids'], $cached['algorithm'], $cached['count'])
            && $cached['algorithm'] === $algorithm
            && $cached['count'] === $count) {
            return $this->publishedQuery()
                ->whereIn('id', $cached['ids'])
                ->get()
                ->sortBy(fn (BlogPost $relatedPost) => array_search($relatedPost->id, $cached['ids'], true))
                ->values();
        }

        $relatedIds = Cache::remember($cacheKey, now()->addDay(), function () use ($post, $count, $algorithm) {
            if ($algorithm === 'recent') {
                $ids = $this->publishedQuery()
                    ->whereKeyNot($post->id)
                    ->latest('published_at')
                    ->limit($count)
                    ->pluck('id')
                    ->values()
                    ->all();

                return ['ids' => $ids, 'algorithm' => 'recent', 'count' => $count];
            }

            $tagIds = $post->tags->pluck('id');
            $categoryIds = $post->categories->pluck('id');

            $primaryMethod = $algorithm === 'category_first' ? 'categories' : 'tags';
            $primaryIds = $algorithm === 'category_first' ? $categoryIds : $tagIds;
            $primaryTable = $algorithm === 'category_first' ? 'blog_categories' : 'blog_tags';

            $secondaryMethod = $algorithm === 'category_first' ? 'tags' : 'categories';
            $secondaryIds = $algorithm === 'category_first' ? $tagIds : $categoryIds;
            $secondaryTable = $algorithm === 'category_first' ? 'blog_tags' : 'blog_categories';

            $related = $this->publishedQuery()
                ->whereKeyNot($post->id)
                ->when($primaryIds->isNotEmpty(), fn ($query) => $query->whereHas($primaryMethod, fn ($q) => $q->whereIn("{$primaryTable}.id", $primaryIds)))
                ->withCount([$primaryMethod . ' as shared_count' => fn ($q) => $q->whereIn("{$primaryTable}.id", $primaryIds)])
                ->orderByDesc('shared_count')
                ->latest('published_at')
                ->limit($count)
                ->get();

            if ($related->count() >= $count) {
                return ['ids' => $related->pluck('id')->values()->all(), 'algorithm' => $algorithm, 'count' => $count];
            }

            $fallback = $this->publishedQuery()
                ->whereKeyNot($post->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->when($secondaryIds->isNotEmpty(), fn ($query) => $query->whereHas($secondaryMethod, fn ($q) => $q->whereIn("{$secondaryTable}.id", $secondaryIds)))
                ->latest('published_at')
                ->limit($count - $related->count())
                ->get();

            $related = $related->concat($fallback);

            if ($related->count() >= $count) {
                return ['ids' => $related->pluck('id')->values()->all(), 'algorithm' => $algorithm, 'count' => $count];
            }

            return [
                'ids' => $related->concat(
                    $this->publishedQuery()
                        ->whereKeyNot($post->id)
                        ->whereNotIn('id', $related->pluck('id'))
                        ->latest('published_at')
                        ->limit($count - $related->count())
                        ->get()
                )->pluck('id')->values()->all(),
                'algorithm' => $algorithm,
                'count' => $count,
            ];
        });

        if (! is_array($relatedIds) || empty($relatedIds['ids'])) {
            Cache::forget($cacheKey);

            return collect();
        }

        return $this->publishedQuery()
            ->whereIn('id', $relatedIds['ids'])
            ->get()
            ->sortBy(fn (BlogPost $relatedPost) => array_search($relatedPost->id, $relatedIds['ids'], true))
            ->values();
    }

    public function refreshCounters(): void
    {
        BlogCategory::query()->update([
            'posts_count' => DB::raw('(
                SELECT COUNT(*)
                FROM blog_post_categories
                JOIN blog_posts ON blog_posts.id = blog_post_categories.post_id
                WHERE blog_post_categories.category_id = blog_categories.id
                AND blog_posts.status = \'published\'
                AND blog_posts.deleted_at IS NULL
            )'),
        ]);

        BlogTag::query()->update([
            'posts_count' => DB::raw('(
                SELECT COUNT(*)
                FROM blog_post_tags
                JOIN blog_posts ON blog_posts.id = blog_post_tags.post_id
                WHERE blog_post_tags.tag_id = blog_tags.id
                AND blog_posts.status = \'published\'
                AND blog_posts.deleted_at IS NULL
            )'),
        ]);
    }

    public function forgetRelatedCache(BlogPost $post): void
    {
        Cache::forget("blog:related:v2:{$post->id}");
    }

    private function uniqueSlug(string $slug, ?int $excludePostId = null): string
    {
        $query = BlogPost::where('slug', $slug);
        if ($excludePostId) {
            $query->where('id', '!=', $excludePostId);
        }

        if (! $query->exists()) {
            return $slug;
        }

        $baseSlug = $slug;
        $suffix = 1;
        do {
            $candidate = "{$baseSlug}-{$suffix}";
            $suffix++;
        } while (BlogPost::where('slug', $candidate)->when($excludePostId, fn ($q) => $q->where('id', '!=', $excludePostId))->exists());

        return $candidate;
    }
}
