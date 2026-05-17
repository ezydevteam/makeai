<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiToolCategory;
use App\Services\AI\ToolCatalogCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AiToolCategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/AI/Categories/Index', [
            'categories' => AiToolCategory::withCount(['templates as tools_count'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateCategory($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        AiToolCategory::create($data);

        return back()->with('success', 'AI tool category created.');
    }

    public function update(Request $request, AiToolCategory $category)
    {
        $data = $this->validateCategory($request, $category->id);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        $category->update($data);

        return back()->with('success', 'AI tool category updated.');
    }

    public function destroy(AiToolCategory $category)
    {
        ToolCatalogCacheService::invalidateForCategory($category);
        $category->templates()->update(['category_id' => null]);
        $category->delete();

        return back()->with('success', 'AI tool category deleted.');
    }

    private function validateCategory(Request $request, ?int $ignoreId = null): array
    {
        $unique = $ignoreId ? "unique:ai_tool_categories,slug,{$ignoreId}" : 'unique:ai_tool_categories,slug';

        return $request->validate([
            'name' => 'required|string|max:255',
            'slug' => "nullable|string|max:100|{$unique}",
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'requires_pro' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);
    }
}
