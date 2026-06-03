<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\Category;
use App\Services\AI\ToolCatalogCacheService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AiAccessController extends Controller
{
    public function index(Request $request)
    {
        $query = AiTool::with('category:id,name,slug')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        return Inertia::render('Admin/AI/AccessSettings/Index', [
            'tools' => $query->paginate(30)->withQueryString(),
            'categories' => Category::aiTools()->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'filters' => $request->only(['category', 'search']),
            'globalDefault' => settings('default_tool_access_level', 'login_required'),
        ]);
    }

    public function bulkUpdate(Request $request)
    {
        $data = $request->validate([
            'tool_ids' => 'required|array',
            'tool_ids.*' => 'exists:ai_tools,id',
            'access_level' => 'required|in:inherit,public,login_required,free_plan,pro_plan',
        ]);

        $tools = AiTool::whereIn('id', $data['tool_ids'])->get();

        AiTool::whereIn('id', $data['tool_ids'])
            ->update(['access_level' => $data['access_level']]);

        $tools->each(fn (AiTool $tool) => ToolCatalogCacheService::invalidateForTool($tool));

        return back()->with('success', translate('Access levels updated successfully.'));
    }

    public function categoryUpdate(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'access_level' => 'required|in:inherit,public,login_required,free_plan,pro_plan',
        ]);

        $category = Category::findOrFail($data['category_id']);
        $tools = AiTool::where('category_id', $category->id)->get();

        AiTool::where('category_id', $category->id)
            ->update(['access_level' => $data['access_level']]);

        ToolCatalogCacheService::invalidateForCategory($category);
        $tools->each(fn (AiTool $tool) => ToolCatalogCacheService::invalidateForTool($tool));

        return back()->with('success', translate('Category access levels updated successfully.'));
    }

    public function presetUpdate(Request $request)
    {
        $preset = $request->validate([
            'preset' => 'required|in:all_public,all_login,all_pro,reset_inherit',
        ])['preset'];

        $level = match ($preset) {
            'all_public' => 'public',
            'all_login' => 'login_required',
            'all_pro' => 'pro_plan',
            'reset_inherit' => 'inherit',
        };

        AiTool::query()->update(['access_level' => $level]);
        ToolCatalogCacheService::invalidateAll();

        return back()->with('success', translate('Preset applied successfully to all tools.'));
    }
}
