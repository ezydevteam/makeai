<?php

namespace App\Http\Controllers\Admin\CMS;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FaqCategoryRequest;
use App\Http\Requests\Admin\FaqGenerateRequest;
use App\Http\Requests\Admin\FaqRequest;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\User;
use App\Services\AI\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    public function index(): Response
    {
        $this->authorizeFaqs();

        $categories = FaqCategory::with(['faqs' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $uncategorized = Faq::whereNull('category_id')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Admin/CMS/Faqs/Index', [
            'categories' => $categories,
            'uncategorized' => $uncategorized,
        ]);
    }

    // ── FAQ Categories ─────────────────────────────────────────────────────

    public function storeCategory(FaqCategoryRequest $request)
    {
        $validated = $request->validated();

        FaqCategory::create([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'],
        ]);

        return back()->with('success', translate('Category created.'));
    }

    public function updateCategory(FaqCategoryRequest $request, FaqCategory $category)
    {
        $validated = $request->validated();

        $category->update([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'],
        ]);

        return back()->with('success', translate('Category updated.'));
    }

    public function destroyCategory(FaqCategory $category)
    {
        $this->authorizeFaqs();
        // Unassign FAQs before deleting category
        Faq::where('category_id', $category->id)->update(['category_id' => null]);
        $category->delete();

        return back()->with('success', translate('Category deleted. FAQs moved to uncategorized.'));
    }

    // ── FAQs ───────────────────────────────────────────────────────────────

    public function store(FaqRequest $request)
    {
        $this->authorizeFaqs();
        $validated = $this->normalizeFaqPayload($request->validated());

        Faq::create($validated);

        return back()->with('success', translate('FAQ created successfully.'));
    }

    public function update(FaqRequest $request, Faq $faq)
    {
        $validated = $this->normalizeFaqPayload($request->validated());

        $faq->update($validated);

        return back()->with('success', translate('FAQ updated successfully.'));
    }

    public function destroy(Faq $faq)
    {
        $this->authorizeFaqs();
        $faq->delete();

        return back()->with('success', translate('FAQ deleted.'));
    }

    public function bulkSort(Request $request)
    {
        $this->authorizeFaqs();

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'exists:faqs,id'],
        ]);

        foreach ($validated['order'] as $position => $id) {
            Faq::where('id', $id)->update(['sort_order' => $position]);
        }

        return back()->with('success', translate('FAQ order saved.'));
    }

    public function toggleActive(Faq $faq)
    {
        $this->authorizeFaqs();
        $faq->update(['is_active' => ! $faq->is_active]);

        return back();
    }



    public function generate(FaqGenerateRequest $request, AiService $aiService): JsonResponse
    {
        $user = User::internalAi();

        $validated = $request->validated();
        $extraPrompt = trim((string) ($validated['prompt'] ?? ''));
        $prompt = "Generate {$validated['count']} helpful FAQ entries for this topic/product: {$validated['topic']}. Return valid JSON array only. Each item must have question and answer. Answers should be concise and may include simple HTML tags like p, strong, em, ul, ol, li, and a.";

        if ($extraPrompt !== '') {
            $prompt .= "\n\nAdditional admin instructions:\n{$extraPrompt}";
        }

        $result = $aiService->complete(
            $user,
            $prompt,
            'You generate FAQ entries for a SaaS CMS. Return JSON only.',
            options: ['max_tokens' => 1600, 'temperature' => 0.45],
            toolSlug: 'admin_faq_generate'
        );

        $items = collect($this->decodeAiJsonArray((string) ($result->content ?? '')))
            ->filter(fn ($item) => is_array($item))
            ->take((int) $validated['count'])
            ->map(function (array $item, int $index) use ($validated) {
                return [
                    'question' => Str::limit(trim(strip_tags((string) ($item['question'] ?? ''))), 500, ''),
                    'answer' => $this->sanitizeAnswer((string) ($item['answer'] ?? '')),
                    'category_id' => $validated['category_id'] ?? null,
                    'is_active' => true,
                    'sort_order' => Faq::max('sort_order') + $index + 1,
                ];
            })
            ->filter(fn ($item) => $item['question'] !== '' && $item['answer'] !== '')
            ->values();

        $items->each(fn ($item) => Faq::create($item));

        return response()->json([
            'success' => true,
            'message' => translate('Generated :count FAQ(s).', ['count' => $items->count()]),
        ]);
    }

    private function authorizeFaqs(): void
    {
        if (! auth('admin')->user()?->hasPermission('content.faq')) {
            abort(403);
        }
    }

    private function normalizeFaqPayload(array $data): array
    {
        $data['answer'] = $this->sanitizeAnswer($data['answer']);

        return $data;
    }

    private function sanitizeAnswer(string $answer): string
    {
        return \App\Services\TiptapHtmlSanitizer::sanitize($answer, \App\Services\TiptapHtmlSanitizer::FAQ_TAGS);
    }

    private function decodeAiJsonArray(string $content): array
    {
        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/(\[.*\])/s', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
