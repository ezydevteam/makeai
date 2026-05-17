<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    public function index(): Response
    {
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

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        FaqCategory::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.uniqid(),
            'sort_order' => $validated['sort_order'],
        ]);

        return back()->with('success', 'Category created.');
    }

    public function updateCategory(Request $request, FaqCategory $category)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $category->update([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'],
        ]);

        return back()->with('success', 'Category updated.');
    }

    public function destroyCategory(FaqCategory $category)
    {
        // Unassign FAQs before deleting category
        Faq::where('category_id', $category->id)->update(['category_id' => null]);
        $category->delete();

        return back()->with('success', 'Category deleted. FAQs moved to uncategorized.');
    }

    // ── FAQs ───────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:faq_categories,id'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        Faq::create($validated);

        return back()->with('success', 'FAQ created successfully.');
    }

    public function update(Request $request, Faq $faq)
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:faq_categories,id'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $faq->update($validated);

        return back()->with('success', 'FAQ updated successfully.');
    }

    public function destroy(Faq $faq)
    {
        $faq->delete();

        return back()->with('success', 'FAQ deleted.');
    }

    public function bulkSort(Request $request)
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'exists:faqs,id'],
        ]);

        foreach ($validated['order'] as $position => $id) {
            Faq::where('id', $id)->update(['sort_order' => $position]);
        }

        return back()->with('success', 'FAQ order saved.');
    }

    public function toggleActive(Faq $faq)
    {
        $faq->update(['is_active' => ! $faq->is_active]);

        return back();
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'category_id' => ['nullable', 'integer', 'exists:faq_categories,id'],
        ]);

        $file = $request->file('csv');
        $rows = array_map('str_getcsv', file($file->getPathname()));
        $header = array_map('strtolower', array_map('trim', array_shift($rows)));
        $imported = 0;

        foreach ($rows as $row) {
            if (count($row) < count($header)) {
                continue;
            }
            $data = array_combine($header, $row);
            Faq::create([
                'question' => $data['question'] ?? $data['q'] ?? 'Untitled',
                'answer' => $data['answer'] ?? $data['a'] ?? '',
                'category_id' => $request->category_id,
                'is_active' => true,
                'sort_order' => 0,
            ]);
            $imported++;
        }

        return back()->with('success', "Imported {$imported} FAQ(s) from CSV.");
    }
}
