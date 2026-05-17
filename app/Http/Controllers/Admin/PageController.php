<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PageController extends Controller
{
    public function index()
    {
        $pages = Page::with('creator')
            ->orderBy('is_system', 'desc')
            ->orderBy('sort_order')
            ->get();

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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:pages,slug',
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string|max:500',
            'template' => 'required|in:default,full_width,blank,landing',
            'featured_image' => 'nullable|image|max:2048',
            'og_image' => 'nullable|image|max:2048',
            'status' => 'required|in:draft,published,scheduled',
            'published_at' => 'nullable|date',
            'password' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:pages,id',
            'sort_order' => 'integer',
            'show_title' => 'boolean',
            'show_breadcrumbs' => 'boolean',
            'show_featured_image' => 'boolean',
            'show_sidebar' => 'boolean',
            'sidebar_position' => 'required|in:left,right',
            'container_width' => 'required|in:default,wide,full,narrow',
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['title']);
        $validated['created_by'] = auth()->id();

        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = $request->file('featured_image')->store('pages', 'public');
        }
        if ($request->hasFile('og_image')) {
            $validated['og_image'] = $request->file('og_image')->store('pages', 'public');
        }

        Page::create($validated);

        return redirect()->route('admin.pages.index')->with('success', 'Page created successfully.');
    }

    public function edit(Page $page)
    {
        return Inertia::render('Admin/CMS/Pages/Editor', [
            'page' => $page,
            'parents' => Page::where('id', '!=', $page->id)->whereNull('parent_id')->where('is_system', false)->get(),
        ]);
    }

    public function update(Request $request, Page $page)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:pages,slug,'.$page->id,
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string|max:500',
            'template' => 'required|in:default,full_width,blank,landing',
            'featured_image' => 'nullable|image|max:2048',
            'og_image' => 'nullable|image|max:2048',
            'status' => 'required|in:draft,published,scheduled',
            'published_at' => 'nullable|date',
            'password' => 'nullable|string|max:255',
            'parent_id' => 'nullable|exists:pages,id',
            'sort_order' => 'integer',
            'show_title' => 'boolean',
            'show_breadcrumbs' => 'boolean',
            'show_featured_image' => 'boolean',
            'show_sidebar' => 'boolean',
            'sidebar_position' => 'required|in:left,right',
            'container_width' => 'required|in:default,wide,full,narrow',
        ]);

        if ($request->hasFile('featured_image')) {
            $validated['featured_image'] = $request->file('featured_image')->store('pages', 'public');
        }
        if ($request->hasFile('og_image')) {
            $validated['og_image'] = $request->file('og_image')->store('pages', 'public');
        }

        $page->update($validated);

        return redirect()->route('admin.pages.index')->with('success', 'Page updated successfully.');
    }

    public function destroy(Page $page)
    {
        if ($page->is_system) {
            return back()->with('error', 'System pages cannot be deleted.');
        }

        $page->delete();

        return redirect()->route('admin.pages.index')->with('success', 'Page moved to trash.');
    }
}
