<?php

namespace App\Http\Controllers\Admin\CMS\Blog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogTagRequest;
use App\Models\BlogTag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BlogTagController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeBlog();

        return Inertia::render('Admin/CMS/Blog/Tags/Index', [
            'tags' => BlogTag::query()
                ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(BlogTagRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        BlogTag::create($data);

        return back()->with('success', translate('Blog tag created.'));
    }

    public function update(BlogTagRequest $request, BlogTag $tag)
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $tag->update($data);

        return back()->with('success', translate('Blog tag updated.'));
    }

    public function destroy(BlogTag $tag)
    {
        $this->authorizeBlog();
        $tag->posts()->detach();
        $tag->delete();

        return back()->with('success', translate('Blog tag deleted.'));
    }

    public function deleteUnused()
    {
        $this->authorizeBlog();
        BlogTag::where('posts_count', 0)->delete();

        return back()->with('success', translate('Unused blog tags deleted.'));
    }

    private function authorizeBlog(): void
    {
    }
}
