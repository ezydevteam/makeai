<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AI\ToolCatalogCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AiToolCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::query()
            ->aiTools()
            ->withCount(['tools as tools_count'])
            ->with('parent:id,name');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && in_array($request->query('status'), ['active', 'inactive'])) {
            $query->where('is_active', $request->query('status') === 'active');
        }

        return Inertia::render('Admin/AI/Categories/Index', [
            'categories' => $query
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'parents' => Category::aiTools()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $request->query('search', ''),
                'status' => $request->query('status', ''),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateCategory($request);
        $data['type'] = 'ai_tool';
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        Category::create($data);

        return back()->with('success', translate('AI tool category created.'));
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validateCategory($request, $category->id);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        $category->update($data);

        return back()->with('success', translate('AI tool category updated.'));
    }

    public function destroy(Category $category)
    {
        if ($category->isSystem()) {
            return back()->with('error', translate('System categories cannot be deleted.'));
        }

        if ($category->hasTools()) {
            return back()->with('error', translate('This category contains tools. Please reassign or delete the tools first.'));
        }

        $category->delete();

        return back()->with('success', translate('AI tool category deleted.'));
    }

    public function toggleActive(Category $category)
    {
        $category->update(['is_active' => !$category->is_active]);

        return back()->with('success', translate('Category status updated.'));
    }

    public function togglePro(Category $category)
    {
        $category->update(['requires_pro' => !$category->requires_pro]);

        return back()->with('success', translate('Category access updated.'));
    }

    public function toggleLogin(Category $category)
    {
        $category->update(['requires_login' => !$category->requires_login]);

        return back()->with('success', translate('Category login status updated.'));
    }

    private function validateCategory(Request $request, ?int $ignoreId = null): array
    {
        $unique = $ignoreId ? "unique:categories,slug,{$ignoreId}" : 'unique:categories,slug';

        return $request->validate([
            'name'             => 'required|string|max:255',
            'slug'             => "nullable|string|max:100|{$unique}",
            'description'      => 'nullable|string|max:1000',
            'icon'             => 'nullable|string|max:100',
            'color'            => 'nullable|string|max:20',
            'is_active'        => 'boolean',
            'requires_pro'     => 'boolean',
            'requires_login'   => 'boolean',
            'sort_order'       => 'nullable|integer|min:0',
            'parent_id'        => 'nullable|integer|exists:categories,id',
            'meta_title'       => 'nullable|string|max:160',
            'meta_description' => 'nullable|string|max:500',
        ]);
    }
}
