<?php

namespace App\Http\Controllers\Admin\CMS\Blog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogCategoryRequest;
use App\Models\BlogCategory;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BlogCategoryController extends Controller
{
    public function index()
    {
        $this->authorizeBlog();

        return Inertia::render('Admin/CMS/Blog/Categories', [
            'categories' => BlogCategory::with('parent:id,name')->orderBy('sort_order')->paginate(25),
            'parents' => BlogCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(BlogCategoryRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        BlogCategory::create($data);

        return back()->with('success', translate('Blog category created.'));
    }

    public function update(BlogCategoryRequest $request, BlogCategory $category)
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $category->update($data);

        return back()->with('success', translate('Blog category updated.'));
    }

    public function destroy(BlogCategory $category)
    {
        $this->authorizeBlog();
        $category->posts()->detach();
        $category->children()->update(['parent_id' => null]);
        $category->delete();

        return back()->with('success', translate('Blog category deleted.'));
    }

    private function authorizeBlog(): void
    {
        if (! auth('admin')->user()?->hasPermission('content.blog')) {
            abort(403, translate('Unauthorized.'));
        }
    }
}
