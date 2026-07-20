<?php

namespace Addons\AiKnowledgeBase\Http\Controllers\Admin;

use Addons\AiKnowledgeBase\Http\Requests\Admin\KbCategoryRequest;
use Addons\AiKnowledgeBase\Models\KbCategory;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class KbCategoryController extends Controller
{
    public function index()
    {
        $categories = KbCategory::withCount('articles')
            ->orderBy('sort_order')
            ->paginate(20);

        return Inertia::render('Addons/ai-knowledge-base/Admin/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(KbCategoryRequest $request)
    {
        KbCategory::create($request->validated());

        return back()->with('success', 'Category created.');
    }

    public function update(KbCategoryRequest $request, KbCategory $category)
    {
        $category->update($request->validated());

        return back()->with('success', 'Category updated.');
    }

    public function destroy(KbCategory $category)
    {
        if ($category->articles()->exists()) {
            return back()->withErrors(['error' => 'Category has articles. Reassign or delete them first.']);
        }

        $category->delete();

        return back()->with('success', 'Category deleted.');
    }
}
