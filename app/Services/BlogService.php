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
        $words = str_word_count(strip_tags($content));

        return max((int) ceil($words / $wordsPerMinute), 1);
    }

    public function excerpt(string $content): string
    {
        $length = max((int) settings('blog_auto_excerpt_length', 160), 40);

        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($content))), $length);
    }

    public function normalizePostData(array $data): array
    {
        $data['slug'] = Str::slug($data['slug'] ?: $data['title']);
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
                'template',
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
        $count = max((int) settings('blog_related_posts_count', 3), 1);

        $cacheKey = "blog:related:v2:{$post->id}:{$count}";
        $relatedIds = Cache::remember($cacheKey, now()->addDay(), function () use ($post, $count) {
            $tagIds = $post->tags->pluck('id');
            $categoryIds = $post->categories->pluck('id');

            $related = $this->publishedQuery()
                ->whereKeyNot($post->id)
                ->when($tagIds->isNotEmpty(), fn ($query) => $query->whereHas('tags', fn ($tagQuery) => $tagQuery->whereIn('blog_tags.id', $tagIds)))
                ->withCount(['tags as shared_tags_count' => fn ($tagQuery) => $tagQuery->whereIn('blog_tags.id', $tagIds)])
                ->orderByDesc('shared_tags_count')
                ->latest('published_at')
                ->limit($count)
                ->get();

            if ($related->count() >= $count) {
                return $related->pluck('id')->values()->all();
            }

            $fallback = $this->publishedQuery()
                ->whereKeyNot($post->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->when($categoryIds->isNotEmpty(), fn ($query) => $query->whereHas('categories', fn ($categoryQuery) => $categoryQuery->whereIn('blog_categories.id', $categoryIds)))
                ->latest('published_at')
                ->limit($count - $related->count())
                ->get();

            $related = $related->concat($fallback);

            if ($related->count() >= $count) {
                return $related->pluck('id')->values()->all();
            }

            return $related->concat(
                $this->publishedQuery()
                    ->whereKeyNot($post->id)
                    ->whereNotIn('id', $related->pluck('id'))
                    ->latest('published_at')
                    ->limit($count - $related->count())
                    ->get()
            )->pluck('id')->values()->all();
        });

        if (! is_array($relatedIds)) {
            Cache::forget($cacheKey);

            return collect();
        }

        return $this->publishedQuery()
            ->whereIn('id', $relatedIds)
            ->get()
            ->sortBy(fn (BlogPost $relatedPost) => array_search($relatedPost->id, $relatedIds, true))
            ->values();
    }

    public function refreshCounters(): void
    {
        BlogCategory::query()->each(function (BlogCategory $category) {
            $category->updateQuietly([
                'posts_count' => $category->posts()->published()->count(),
            ]);
        });

        BlogTag::query()->each(function (BlogTag $tag) {
            $tag->updateQuietly([
                'posts_count' => $tag->posts()->published()->count(),
            ]);
        });
    }

    public function forgetRelatedCache(BlogPost $post): void
    {
        Cache::forget("blog:related:{$post->id}:".max((int) settings('blog_related_posts_count', 3), 1));
        Cache::forget("blog:related:v2:{$post->id}:".max((int) settings('blog_related_posts_count', 3), 1));
    }
}
