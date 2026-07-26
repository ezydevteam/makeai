<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageAiAssistRequest;
use App\Http\Requests\Admin\PageRequest;
use App\Models\Page;
use App\Models\User;
use App\Services\AI\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PageController extends Controller
{
    public function index(Request $request)
    {
        $query = Page::query()->with('creator');

        if ($request->string('status')->toString() === 'trashed') {
            $query->onlyTrashed();
        } else {
            $query->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()));
        }

        $query->when($request->filled('search'), function ($query) use ($request) {
            $search = trim($request->string('search')->toString());
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%"));
        });

        $pages = $query
            ->orderBy('is_system', 'desc')
            ->orderBy('sort_order')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/CMS/Pages/Index', [
            'pages' => $pages,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/CMS/Pages/Editor', [
            'page' => null,
            'parents' => Page::whereNull('parent_id')->where('is_system', false)->get(),
        ]);
    }

    public function store(PageRequest $request)
    {
        $validated = $this->normalizePageData($request->validated());
        $validated['created_by'] = auth('admin')->id();

        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = store_public_upload($request->file('featured_image'), 'pages');
        }
        if ($request->hasFile('og_image')) {
            $validated['og_image'] = store_public_upload($request->file('og_image'), 'pages');
        }

        Page::create($validated);

        return redirect()->route('admin.pages.index')->with('success', translate('Page created successfully.'));
    }

    public function edit(Page $page)
    {
        return Inertia::render('Admin/CMS/Pages/Editor', [
            'page' => array_merge($page->toArray(), [
                'has_password' => filled($page->password),
            ]),
            'parents' => Page::where('id', '!=', $page->id)->whereNull('parent_id')->where('is_system', false)->get(),
        ]);
    }

    public function update(PageRequest $request, Page $page)
    {
        $validated = $this->normalizePageData($request->validated());

        // System pages have fixed slugs referenced by hardcoded links (footer,
        // GDPR cookie banner, menus, legal pages). Keep the slug immutable so a
        // rename can't silently break those references — content stays editable.
        if ($page->is_system) {
            $validated['slug'] = $page->slug;
        }

        if ($request->boolean('remove_featured_image')) {
            if ($page->featured_image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($page->featured_image);
            }
            $validated['featured_image'] = null;
        } elseif ($request->hasFile('featured_image')) {
            $validated['featured_image'] = store_public_upload($request->file('featured_image'), 'pages', $page->featured_image);
        }

        if ($request->boolean('remove_og_image')) {
            if ($page->og_image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($page->og_image);
            }
            $validated['og_image'] = null;
        } elseif ($request->hasFile('og_image')) {
            $validated['og_image'] = store_public_upload($request->file('og_image'), 'pages', $page->og_image);
        }

        $page->update($validated);

        return redirect()->route('admin.pages.index')->with('success', translate('Page updated successfully.'));
    }

    public function aiAssist(PageAiAssistRequest $request, AiService $aiService): JsonResponse
    {
        $validated = $request->validated();
        $action = $validated['action'];
        $title = trim($validated['title'] ?? '');
        $content = Str::limit(strip_tags($validated['content'] ?? ''), 12000, '');
        $selectedText = trim(strip_tags($validated['selected_text'] ?? ''));

        if (in_array($action, $this->selectionAiActions(), true) && $action !== 'continue_writing' && $selectedText === '') {
            return response()->json([
                'success' => false,
                'code' => 'EMPTY_SELECTION',
                'message' => translate('Select text before using this AI action.'),
            ], 422);
        }

        if (! in_array($action, ['generate_content', ...$this->selectionAiActions()], true) && $content === '' && $selectedText === '') {
            return response()->json([
                'success' => false,
                'code' => 'EMPTY_CONTENT',
                'message' => translate('Add page content before using this AI assist action.'),
            ], 422);
        }

        $user = User::internalAi();

        $result = $aiService->complete(
            $user,
            $this->aiAssistPrompt($action, $title, $content, $selectedText),
            'You are an editorial assistant for a CMS page builder. Return only the requested content, with no preamble.',
            options: ['max_tokens' => 900, 'temperature' => 0.45],
            toolSlug: 'admin_page_assist'
        );

        return response()->json([
            'success' => true,
            'data' => $this->formatAiAssistResult($action, trim($result->content ?? '')),
            'message' => translate('AI assist completed.'),
        ]);
    }

    public function preview(Page $page)
    {
        $page->load('parent');
        return Inertia::render('Page', [
            'page' => $page,
            'seo' => [
                'title' => $page->title.' ['.translate('Preview').']',
                'description' => $page->meta_description ?: $page->excerpt,
                'keywords' => $page->meta_keywords,
                'canonical' => route('page.show', $page->slug),
                'robots' => 'noindex,nofollow',
                'og_image' => $page->og_image ? url(media_url($page->og_image)) : null,
                'schema' => [],
            ],
            'contactSettings' => null,
        ]);
    }

    public function destroy(Page $page)
    {
        $this->authorizePages();

        if ($page->is_system) {
            return back()->with('error', translate('System pages cannot be deleted.'));
        }

        $page->delete();

        return redirect()->route('admin.pages.index')->with('success', translate('Page moved to trash.'));
    }

    public function restore(Page $page)
    {
        $this->authorizePages();
        $page->restore();

        return back()->with('success', translate('Page restored.'));
    }

    public function forceDelete(Page $page)
    {
        $this->authorizePages();

        if ($page->is_system) {
            return back()->with('error', translate('System pages cannot be permanently deleted.'));
        }

        $page->forceDelete();

        return back()->with('success', translate('Page permanently deleted.'));
    }

    private function aiAssistPrompt(string $action, string $title, string $content, string $selectedText): string
    {
        return match ($action) {
            'generate_title' => "Generate one concise CMS page title under 65 characters from this content. Return plain text only.\n\nContent:\n{$content}",
            'generate_content' => "Write polished CMS page body content in clean HTML paragraphs and headings. Use the title as context when available. Return HTML only.\n\nTitle: {$title}\n\nExisting content:\n{$content}",
            'generate_excerpt' => "Write a concise CMS page excerpt between 140 and 160 characters. Return plain text only.\n\nTitle: {$title}\n\nContent:\n{$content}",
            'generate_seo' => "Generate SEO metadata for this CMS page. Return valid JSON only with keys meta_title, meta_description, and meta_keywords. meta_title must be 50-60 characters; meta_description must be 140-160 characters; meta_keywords must be a comma-separated string.\n\nTitle: {$title}\n\nContent:\n{$content}",
            'improve_selection' => "Improve the selected CMS page text for clarity, flow, and readability. Keep the meaning intact. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'shorten_selection' => "Shorten the selected CMS page text while preserving the main meaning. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'expand_selection' => "Expand the selected CMS page text with useful detail and smoother flow. Return clean HTML only.\n\nTitle: {$title}\n\nSelected text:\n{$selectedText}",
            'rephrase_selection' => "Rephrase the selected CMS page text while preserving the original meaning. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'translate_selection' => "Translate the selected CMS page text into clear English unless the selected text is already English; if it is already English, translate it into Bengali. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'change_tone' => "Rewrite the selected CMS page text in a professional, friendly tone. Preserve the meaning. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'summarize_selection' => "Summarize the selected CMS page text into a concise version. Preserve key points. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'fix_grammar' => "Fix grammar, spelling, punctuation, and awkward phrasing in the selected CMS page text. Preserve the meaning. Return clean HTML only.\n\nSelected text:\n{$selectedText}",
            'continue_writing' => "Continue writing this CMS page from the cursor with 2 short, useful paragraphs. Return clean HTML only.\n\nTitle: {$title}\n\nExisting content:\n{$content}",
        };
    }

    private function formatAiAssistResult(string $action, string $content): array
    {
        if ($action === 'generate_seo') {
            $json = $this->decodeAiJson($content);

            return [
                'meta_title' => Str::limit((string) ($json['meta_title'] ?? ''), 255, ''),
                'meta_description' => (string) ($json['meta_description'] ?? ''),
                'meta_keywords' => Str::limit((string) ($json['meta_keywords'] ?? ''), 500, ''),
            ];
        }

        if (in_array($action, ['generate_content', ...$this->selectionAiActions()], true)) {
            return [
                'content' => \App\Services\TiptapHtmlSanitizer::sanitize($content, \App\Services\TiptapHtmlSanitizer::BASIC_TAGS),
            ];
        }

        return ['content' => trim(strip_tags($content), "\"' \n\r\t\v\0")];
    }

    private function selectionAiActions(): array
    {
        return [
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

        if (preg_match('/(\{.*\})/s', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizePageData(array $data): array
    {
        $data['slug'] = Str::slug($data['slug'] ?: $data['title']);
        $data['content'] = $this->sanitizeHtml($data['content']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        unset($data['featured_image'], $data['og_image']);

        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        if ($data['status'] !== 'scheduled' && empty($data['published_at'])) {
            $data['published_at'] = null;
        }

        // The field is the single source of truth for protection: a value sets it,
        // blank removes it. Previously a blank value was unset on update to "keep the
        // current password", which left no way at all to unprotect a page again.
        $data['password'] = filled($data['password'] ?? null)
            ? Hash::make($data['password'])
            : null;

        return $data;
    }

    private function sanitizeHtml(string $html): string
    {
        return \App\Services\TiptapHtmlSanitizer::sanitize($html);
    }

    private function authorizePages(): void
    {
        if (! auth('admin')->user()?->hasPermission('content.pages')) {
            abort(403);
        }
    }
}
