<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageAiAssistRequest;
use App\Http\Requests\Admin\PageRequest;
use App\Models\Page;
use App\Models\User;
use App\Services\AI\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PageController extends Controller
{
    public function index()
    {
        $pages = Page::with('creator')
            ->orderBy('is_system', 'desc')
            ->orderBy('sort_order')
            ->paginate(25);

        return Inertia::render('Admin/CMS/Pages/Index', [
            'pages' => $pages,
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
            $validated['featured_image'] = $request->file('featured_image')->store('pages', 'public');
        }
        if ($request->hasFile('og_image')) {
            $validated['og_image'] = $request->file('og_image')->store('pages', 'public');
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
        $validated = $this->normalizePageData($request->validated(), $page);

        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = $request->file('featured_image')->store('pages', 'public');
        }
        if ($request->hasFile('og_image')) {
            $validated['og_image'] = $request->file('og_image')->store('pages', 'public');
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

        $user = User::query()->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'code' => 'AI_USER_MISSING',
                'message' => translate('No user account is available to process AI requests.'),
            ], 422);
        }

        $result = $aiService->complete(
            $user,
            $this->aiAssistPrompt($action, $title, $content, $selectedText),
            'You are an editorial assistant for a CMS page builder. Return only the requested content, with no preamble.',
            settings('default_ai_provider', 'openai'),
            settings('default_ai_model', 'gpt-4o-mini'),
            ['max_tokens' => 900, 'temperature' => 0.45]
        );

        return response()->json([
            'success' => true,
            'data' => $this->formatAiAssistResult($action, trim($result['content'] ?? '')),
            'message' => translate('AI assist completed.'),
        ]);
    }

    public function destroy(Page $page)
    {
        if ($page->is_system) {
            return back()->with('error', translate('System pages cannot be deleted.'));
        }

        $page->delete();

        return redirect()->route('admin.pages.index')->with('success', translate('Page moved to trash.'));
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
                'content' => strip_tags($content, '<p><br><strong><b><em><i><u><ul><ol><li><blockquote><h2><h3><h4><h5>'),
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

    private function normalizePageData(array $data, ?Page $page = null): array
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

        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } elseif ($page) {
            unset($data['password']);
        } else {
            $data['password'] = null;
        }

        return $data;
    }

    private function sanitizeHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html) ?? '';
        $html = preg_replace('/\son\w+=("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/javascript:/i', '', $html) ?? '';

        return strip_tags($html, '<p><br><strong><b><em><i><u><s><ul><ol><li><blockquote><h2><h3><h4><h5><a><img><table><thead><tbody><tr><th><td><pre><code><hr>');
    }
}
