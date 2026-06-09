<?php

namespace App\Http\Controllers;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Comment;
use App\Services\BlogService;
use App\Services\SocialService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function __construct(
        private readonly BlogService $blog,
        private readonly SocialService $social,
    ) {}

    public function index(Request $request): Response
    {
        $posts = $this->filteredPosts($request)->paginate($this->postsPerPage())->withQueryString();

        return Inertia::render('Blog/Index', [
            'posts' => $posts,
            'categories' => $this->sidebarCategories(),
            'tags' => $this->sidebarTags(),
            'filters' => $request->only(['search', 'sort']),
            'heading' => translate('Blog'),
            'description' => translate('Latest articles, guides, and product updates.'),
            'meta' => $this->meta(translate('Blog'), translate('Latest articles, guides, and product updates.')),
        ]);
    }

    public function category(Request $request, string $slug): Response
    {
        $category = BlogCategory::active()->where('slug', $slug)->firstOrFail();
        $posts = $this->filteredPosts($request)
            ->whereHas('categories', fn ($query) => $query->where('blog_categories.id', $category->id))
            ->paginate($this->postsPerPage())
            ->withQueryString();

        return Inertia::render('Blog/Index', [
            'posts' => $posts,
            'categories' => $this->sidebarCategories(),
            'tags' => $this->sidebarTags(),
            'filters' => $request->only(['search', 'sort']),
            'activeCategory' => $category,
            'heading' => $category->name,
            'description' => $category->description,
            'meta' => $this->meta($category->meta_title ?: $category->name, $category->meta_description ?: $category->description),
        ]);
    }

    public function tag(Request $request, string $slug): Response
    {
        $tag = BlogTag::where('slug', $slug)->firstOrFail();
        $posts = $this->filteredPosts($request)
            ->whereHas('tags', fn ($query) => $query->where('blog_tags.id', $tag->id))
            ->paginate($this->postsPerPage())
            ->withQueryString();

        return Inertia::render('Blog/Index', [
            'posts' => $posts,
            'categories' => $this->sidebarCategories(),
            'tags' => $this->sidebarTags(),
            'filters' => $request->only(['search', 'sort']),
            'activeTag' => $tag,
            'heading' => $tag->name,
            'description' => $tag->description,
            'meta' => $this->meta($tag->meta_title ?: $tag->name, $tag->meta_description ?: $tag->description),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $post = $this->blog->publishedQuery()
            ->where('slug', $slug)
            ->withCount('favorites')
            ->first();

        if (! $post && auth('admin')->check()) {
            $previewPost = BlogPost::query()
                ->where('slug', $slug)
                ->with(['author:id,name,avatar', 'categories:id,name,slug,color', 'tags:id,name,slug'])
                ->firstOrFail();

            $this->authorize('preview', $previewPost);

            $previewPost->setAttribute('comments_count', $previewPost->comments()->approved()->count());

            return Inertia::render('Blog/Show', [
                'post' => $previewPost,
                'relatedPosts' => $previewPost->show_related_posts ? $this->blog->relatedPosts($previewPost) : [],
                'share' => null,
                'comments' => [
                    'data' => [],
                    'links' => [],
                    'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0],
                ],
                'commentSettings' => [
                    'enabled' => false,
                    'allow_guests' => false,
                    'poll_seconds' => 60,
                ],
                'schema' => [],
                'meta' => $this->meta(
                    $previewPost->title.' ['.translate('Preview').']',
                    $previewPost->excerpt ?? '',
                    true
                ),
            ]);
        }

        abort_if(! $post, 404);

        $post->setAttribute('is_favorited', $request->user()
            ? $post->favorites()->where('user_id', $request->user()->id)->exists()
            : false);

        $this->blog->trackView($post, $request->user()?->id, $request->ip());

        return Inertia::render('Blog/Show', [
            'post' => $post,
            'relatedPosts' => $post->show_related_posts ? $this->blog->relatedPosts($post) : [],
            'share' => $post->show_share_buttons
                ? $this->social->sharePayload(route('blog.show', $post->slug), $post->title, $post->og_image ?: $post->featured_image)
                : null,
            'comments' => $this->commentsPayload($post, $request),
            'commentSettings' => [
                'enabled' => (bool) settings('comments_enabled', true) && (bool) $post->allow_comments,
                'allow_guests' => (bool) settings('comments_allow_guests', false),
                'poll_seconds' => (int) settings('comments_poll_seconds', 60),
            ],
            'schema' => $this->schema($post),
            'meta' => $this->meta(
                $post->meta_title ?: $post->title,
                $post->meta_description ?: $post->excerpt,
                $post->no_index
            ),
        ]);
    }

    public function rss()
    {
        $posts = $this->blog->publishedQuery()
            ->latest('published_at')
            ->limit(max((int) settings('blog_rss_posts_count', 20), 1))
            ->get();

        $xml = view('rss.blog', [
            'posts' => $posts,
            'title' => settings('app_name', 'App').' '.translate('Blog'),
            'description' => translate('Latest blog posts'),
            'updated' => optional($posts->first()?->published_at)->toRfc2822String() ?: now()->toRfc2822String(),
        ])->render();

        return ResponseFacade::make($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    private function filteredPosts(Request $request)
    {
        return $this->blog->publishedQuery()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->when($request->string('sort')->toString() === 'popular', fn ($query) => $query->orderByDesc('views_count'))
            ->when($request->string('sort')->toString() === 'commented', fn ($query) => $query->orderByDesc('comments_count'))
            ->when(! in_array($request->string('sort')->toString(), ['popular', 'commented'], true), fn ($query) => $query->orderByDesc('is_sticky')->latest('published_at'));
    }

    private function postsPerPage(): int
    {
        return max((int) settings('blog_posts_per_page', 9), 1);
    }

    private function sidebarCategories()
    {
        return BlogCategory::active()
            ->where('posts_count', '>', 0)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'color', 'posts_count']);
    }

    private function sidebarTags()
    {
        return BlogTag::where('posts_count', '>', 0)
            ->orderByDesc('posts_count')
            ->limit(30)
            ->get(['id', 'name', 'slug', 'posts_count']);
    }

    private function commentsPayload(BlogPost $post, Request $request): array
    {
        $comments = $post->comments()
            ->approved()
            ->whereNull('parent_id')
            ->with([
                'user:id,name,avatar',
                'likes:id,comment_id,user_id,ip_hash',
                'replies' => fn ($query) => $query->approved()->with(['user:id,name,avatar', 'likes:id,comment_id,user_id,ip_hash']),
            ])
            ->withCount('reports')
            ->oldest()
            ->paginate(10, ['*'], 'comments_page')
            ->through(fn (Comment $comment) => $this->commentPayload($comment, $request))
            ->withQueryString();

        return [
            'data' => $comments->items(),
            'links' => $comments->linkCollection(),
            'meta' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'total' => $comments->total(),
            ],
        ];
    }

    private function commentPayload(Comment $comment, Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'created_at' => $comment->created_at?->toISOString(),
            'likes_count' => (int) $comment->likes_count,
            'liked' => $this->commentLikedByViewer($comment, $request),
            'can_edit' => $comment->canBeEditedBy($user),
            'can_delete' => $comment->user_id !== null && $comment->user_id === $user?->id,
            'reports_count' => $comment->reports_count ?? 0,
            'user' => $comment->user ? [
                'name' => $comment->user->name,
                'avatar' => $comment->user->avatar,
            ] : null,
            'guest_name' => $comment->guest_name,
            'replies' => $comment->replies
                ->map(fn (Comment $reply) => $this->commentPayload($reply, $request))
                ->values()
                ->all(),
        ];
    }

    private function commentLikedByViewer(Comment $comment, Request $request): bool
    {
        $user = $request->user();

        return $comment->likes->contains(function ($like) use ($user, $request) {
            if ($user) {
                return $like->user_id === $user->id;
            }

            return $like->ip_hash === hash('sha256', $request->ip().'|'.config('app.key'));
        });
    }

    private function meta(string $title, ?string $description, bool $noIndex = false): array
    {
        return [
            'title' => $title,
            'description' => $description ?: '',
            'no_index' => $noIndex,
            'rss' => route('blog.rss'),
        ];
    }

    private function schema(BlogPost $post): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => $post->schema_type,
            'headline' => $post->title,
            'description' => $post->meta_description ?: $post->excerpt,
            'image' => $post->og_image ?: $post->featured_image,
            'datePublished' => optional($post->published_at)->toAtomString(),
            'dateModified' => Carbon::parse($post->updated_at)->toAtomString(),
            'author' => [
                '@type' => 'Person',
                'name' => $post->author?->name,
            ],
            'mainEntityOfPage' => route('blog.show', $post->slug),
        ];
    }
}
