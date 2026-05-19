<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogPostAiAssistRequest;
use App\Http\Requests\Admin\BlogPostAutosaveRequest;
use App\Http\Requests\Admin\BlogPostRequest;
use App\Models\Admin;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\BlogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BlogPostController extends Controller
{
    public function __construct(private readonly BlogService $blog) {}

    public function index(Request $request)
    {
        $this->authorizeBlog();

        $posts = BlogPost::query()
            ->with(['author:id,name', 'categories:id,name,slug'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());
                $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('category'), fn ($query) => $query->whereHas('categories', fn ($q) => $q->where('blog_categories.id', $request->integer('category'))))
            ->when($request->filled('author'), fn ($query) => $query->where('author_id', $request->integer('author')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Blog/Posts/Index', [
            'posts' => $posts,
            'categories' => BlogCategory::orderBy('sort_order')->get(['id', 'name']),
            'authors' => Admin::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'status', 'category', 'author']),
        ]);
    }

    public function create()
    {
        $this->authorizeBlog();

        return Inertia::render('Admin/Blog/Posts/Editor', [
            'post' => null,
            'categories' => BlogCategory::active()->orderBy('sort_order')->get(['id', 'name']),
            'tags' => BlogTag::orderBy('name')->get(['name']),
            'authors' => Admin::orderBy('name')->get(['id', 'name']),
            'defaults' => $this->defaults(),
        ]);
    }

    public function store(BlogPostRequest $request)
    {
        $validated = $this->blog->normalizePostData($request->validated());
        $validated = $this->storeUploadedFeaturedImage($request, $validated);
        $categoryIds = $validated['category_ids'] ?? [];
        $tagNames = $validated['tags'] ?? [];
        unset($validated['category_ids'], $validated['tags'], $validated['featured_image_file']);

        DB::transaction(function () use ($validated, $categoryIds, $tagNames, $request) {
            $post = BlogPost::create($validated);
            $this->blog->syncRelations($post, $categoryIds, $tagNames);
            $this->blog->saveRevision($post, $request->user('admin')?->id);
        });

        return redirect()->route('admin.blog.posts.index')->with('success', translate('Blog post created.'));
    }

    public function edit(BlogPost $post)
    {
        $this->authorizeBlog();
        $post->load(['categories:id,name', 'tags:id,name', 'revisions.admin:id,name']);

        return Inertia::render('Admin/Blog/Posts/Editor', [
            'post' => $post,
            'categories' => BlogCategory::active()->orderBy('sort_order')->get(['id', 'name']),
            'tags' => BlogTag::orderBy('name')->get(['name']),
            'authors' => Admin::orderBy('name')->get(['id', 'name']),
            'defaults' => $this->defaults(),
        ]);
    }

    public function update(BlogPostRequest $request, BlogPost $post)
    {
        $validated = $this->blog->normalizePostData($request->validated());
        $validated = $this->storeUploadedFeaturedImage($request, $validated);
        $categoryIds = $validated['category_ids'] ?? [];
        $tagNames = $validated['tags'] ?? [];
        unset($validated['category_ids'], $validated['tags'], $validated['featured_image_file']);

        DB::transaction(function () use ($post, $validated, $categoryIds, $tagNames, $request) {
            $post->update($validated);
            $this->blog->syncRelations($post, $categoryIds, $tagNames);
            $this->blog->saveRevision($post->fresh(), $request->user('admin')?->id);
        });

        return redirect()->route('admin.blog.posts.index')->with('success', translate('Blog post updated.'));
    }

    public function autosave(BlogPostAutosaveRequest $request, ?BlogPost $post = null): JsonResponse
    {
        $validated = $this->blog->normalizePostData($this->autosavePayload($request->validated(), $post));
        $categoryIds = $validated['category_ids'] ?? [];
        $tagNames = $validated['tags'] ?? [];
        unset($validated['category_ids'], $validated['tags']);

        $post = DB::transaction(function () use ($post, $validated, $categoryIds, $tagNames, $request) {
            if ($post) {
                $post->fill($validated);
                $hasChanges = $post->isDirty();
                $post->save();
            } else {
                $post = BlogPost::create($validated);
                $hasChanges = true;
            }

            $this->blog->syncRelations($post, $categoryIds, $tagNames);

            if ($hasChanges) {
                $this->blog->saveRevision($post->fresh(), $request->user('admin')?->id);
            }

            return $post->fresh();
        });

        return response()->json([
            'success' => true,
            'data' => [
                'ulid' => $post->ulid,
                'slug' => $post->slug,
                'saved_at' => now()->toIso8601String(),
                'edit_url' => route('admin.blog.posts.edit', $post),
            ],
            'message' => translate('Draft auto-saved.'),
        ]);
    }

    public function aiAssist(BlogPostAiAssistRequest $request, AiService $aiService): JsonResponse
    {
        $validated = $request->validated();
        $action = $validated['action'];
        $content = Str::limit(strip_tags($validated['content'] ?? ''), 12000, '');
        $selectedText = trim(strip_tags($validated['selected_text'] ?? ''));
        $title = trim($validated['title'] ?? '');

        if ($content === '' && ! in_array($action, $this->selectionAiActions(), true)) {
            return response()->json([
                'success' => false,
                'code' => 'EMPTY_CONTENT',
                'message' => translate('Add content before using AI assist.'),
            ], 422);
        }

        if (in_array($action, $this->selectionAiActions(), true) && $action !== 'continue_writing' && $selectedText === '') {
            return response()->json([
                'success' => false,
                'code' => 'EMPTY_SELECTION',
                'message' => translate('Select text before using this AI action.'),
            ], 422);
        }

        $user = User::query()->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'code' => 'AI_USER_MISSING',
                'message' => translate('No user account is available to process AI requests.'),
            ], 422);
        }

        $prompt = $this->aiAssistPrompt($action, $title, $content, $selectedText);
        $result = $aiService->complete(
            $user,
            $prompt,
            'You are an editorial assistant for an AI SaaS blog CMS. Return only the requested content, with no preamble.',
            settings('default_ai_provider', 'openai'),
            settings('default_ai_model', 'gpt-4o-mini'),
            ['max_tokens' => 700, 'temperature' => 0.4]
        );

        return response()->json([
            'success' => true,
            'data' => $this->formatAiAssistResult($action, trim($result['content'] ?? '')),
            'message' => translate('AI assist completed.'),
        ]);
    }

    public function destroy(BlogPost $post)
    {
        $this->authorizeBlog();
        $post->delete();
        $this->blog->refreshCounters();

        return back()->with('success', translate('Blog post moved to trash.'));
    }

    public function duplicate(BlogPost $post)
    {
        $this->authorizeBlog();

        $copy = $post->replicate(['ulid', 'views_count']);
        $copy->title = $post->title.' '.translate('Copy');
        $copy->slug = Str::limit($post->slug.'-copy-'.Str::lower(Str::random(6)), 500, '');
        $copy->status = 'draft';
        $copy->published_at = null;
        $copy->scheduled_at = null;
        $copy->views_count = 0;
        $copy->save();
        $copy->categories()->sync($post->categories()->pluck('blog_categories.id'));
        $copy->tags()->sync($post->tags()->pluck('blog_tags.id'));
        $this->blog->saveRevision($copy, auth('admin')->id());

        return redirect()->route('admin.blog.posts.edit', $copy)->with('success', translate('Blog post duplicated.'));
    }

    public function bulk(Request $request)
    {
        $this->authorizeBlog();

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'string', 'exists:blog_posts,ulid'],
            'action' => ['required', 'in:publish,draft,delete'],
        ]);

        $query = BlogPost::whereIn('ulid', $validated['ids']);

        match ($validated['action']) {
            'publish' => $query->update(['status' => 'published', 'published_at' => now()]),
            'draft' => $query->update(['status' => 'draft']),
            'delete' => $query->delete(),
        };

        $this->blog->refreshCounters();

        return back()->with('success', translate('Bulk action applied.'));
    }

    public function export(): StreamedResponse
    {
        $this->authorizeBlog();

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['title', 'slug', 'status', 'author', 'views', 'published_at']);
            BlogPost::with('author:id,name')->orderBy('title')->chunk(200, function ($posts) use ($handle) {
                foreach ($posts as $post) {
                    fputcsv($handle, [
                        $post->title,
                        $post->slug,
                        $post->status,
                        $post->author?->name,
                        $post->views_count,
                        optional($post->published_at)->toDateTimeString(),
                    ]);
                }
            });
            fclose($handle);
        }, 'blog-posts.csv', ['Content-Type' => 'text/csv']);
    }

    private function defaults(): array
    {
        return [
            'author_id' => (int) settings('blog_default_author', auth('admin')->id()),
            'allow_comments' => (bool) settings('blog_default_allow_comments', true),
            'show_reading_time' => (bool) settings('blog_show_reading_time', true),
        ];
    }

    private function autosavePayload(array $data, ?BlogPost $post): array
    {
        $title = trim($data['title'] ?? '');
        $isUntitledDraft = $title === '';

        $data['title'] = $isUntitledDraft ? translate('Untitled draft') : $title;
        $data['slug'] = trim($data['slug'] ?? '') ?: $this->autosaveSlug($data['title'], $post, $isUntitledDraft);
        $data['content'] = $data['content'] ?? '';
        $data['excerpt'] = $data['excerpt'] ?? null;
        $data['status'] = $post ? ($data['status'] ?? $post->status) : 'draft';

        return $data;
    }

    private function autosaveSlug(string $title, ?BlogPost $post, bool $isUntitledDraft): string
    {
        if ($post?->slug) {
            return $post->slug;
        }

        $baseSlug = $isUntitledDraft ? 'untitled-draft' : Str::slug($title);
        $baseSlug = $baseSlug ?: 'draft';
        $suffix = $isUntitledDraft ? 1 : 0;

        do {
            $slug = $suffix > 0 ? "{$baseSlug}-{$suffix}" : $baseSlug;
            $suffix++;
        } while (BlogPost::where('slug', $slug)->exists());

        return $slug;
    }

    private function aiAssistPrompt(string $action, string $title, string $content, string $selectedText): string
    {
        return match ($action) {
            'generate_title' => "Generate one compelling blog post title under 70 characters from this content. Return plain text only.\n\nContent:\n{$content}",
            'generate_excerpt' => "Write a concise blog excerpt between 140 and 160 characters from this content. Return plain text only.\n\nTitle: {$title}\n\nContent:\n{$content}",
            'generate_seo' => "Generate SEO metadata for this blog post. Return valid JSON only with keys meta_title and meta_description. meta_title must be 50-60 characters; meta_description must be 140-160 characters.\n\nTitle: {$title}\n\nContent:\n{$content}",
            'generate_tags' => "Generate 5 to 8 concise blog tags from this content. Return a valid JSON array of strings only.\n\nTitle: {$title}\n\nContent:\n{$content}",
            'improve_paragraph' => "Improve the selected paragraph for clarity, flow, and readability. Keep the meaning intact. Return clean HTML paragraph content only.\n\nSelected paragraph:\n{$selectedText}",
            'improve_selection' => "Improve the selected blog text for clarity, flow, and readability. Keep the meaning intact. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'shorten_selection' => "Shorten the selected blog text while preserving the main meaning. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'expand_selection' => "Expand the selected blog text with useful detail and smoother flow. Keep it relevant to the current post. Return clean HTML only.\n\nTitle: {$title}\n\nSelected text:\n{$selectedText}",
            'rephrase_selection' => "Rephrase the selected blog text while preserving the original meaning. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'translate_selection' => "Translate the selected blog text into clear English unless the selected text is already English; if it is already English, translate it into Bengali. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'change_tone' => "Rewrite the selected blog text in a professional, friendly tone. Preserve the meaning. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'summarize_selection' => "Summarize the selected blog text into a concise version. Preserve key points. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'fix_grammar' => "Fix grammar, spelling, punctuation, and awkward phrasing in the selected blog text. Preserve the meaning. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'continue_writing' => "Continue this blog post from the cursor with 2 short, useful paragraphs. Return clean HTML paragraphs only.\n\nTitle: {$title}\n\nExisting content:\n{$content}",
        };
    }

    private function formatAiAssistResult(string $action, string $content): array
    {
        if ($action === 'generate_seo') {
            $json = $this->decodeAiJson($content);

            return [
                'meta_title' => Str::limit((string) ($json['meta_title'] ?? ''), 255, ''),
                'meta_description' => (string) ($json['meta_description'] ?? ''),
            ];
        }

        if ($action === 'generate_tags') {
            $json = $this->decodeAiJson($content);

            return [
                'tags' => collect(is_array($json) ? $json : [])
                    ->filter(fn ($tag) => is_string($tag))
                    ->map(fn ($tag) => trim($tag))
                    ->filter()
                    ->take(8)
                    ->values()
                    ->all(),
            ];
        }

        if (in_array($action, $this->selectionAiActions(), true)) {
            return [
                'content' => strip_tags($content, '<p><br><strong><b><em><i><u><ul><ol><li><blockquote><h2><h3><h4>'),
            ];
        }

        return ['content' => trim(strip_tags($content), "\"' \n\r\t\v\0")];
    }

    private function selectionAiActions(): array
    {
        return [
            'improve_paragraph',
            'improve_selection',
            'shorten_selection',
            'expand_selection',
            'rephrase_selection',
            'translate_selection',
            'change_tone',
            'summarize_selection',
            'fix_grammar',
            'continue_writing',
        ];
    }

    private function decodeAiJson(string $content): array
    {
        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/(\{.*\}|\[.*\])/s', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function storeUploadedFeaturedImage(BlogPostRequest $request, array $validated): array
    {
        if (! $request->hasFile('featured_image_file')) {
            return $validated;
        }

        $path = $request->file('featured_image_file')->store('blog', 'public');
        $validated['featured_image'] = Storage::url($path);

        return $validated;
    }

    private function authorizeBlog(): void
    {
        abort_unless(auth('admin')->user()?->hasPermission('content.blog'), 403);
    }
}
