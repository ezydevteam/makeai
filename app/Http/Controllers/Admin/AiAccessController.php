<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiTemplate;
use App\Models\AiToolCategory;
use App\Services\AI\ToolCatalogCacheService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AiAccessController extends Controller
{
    /**
     * Display the access settings grid.
     */
    public function index(Request $request)
    {
        $query = AiTemplate::with('toolCategory:id,name,slug')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        return Inertia::render('Admin/AI/AccessSettings/Index', [
            'templates' => $query->paginate(30)->withQueryString(),
            'categories' => AiToolCategory::orderBy('sort_order')->get(['id', 'name', 'slug']),
            'filters' => $request->only(['category', 'search']),
            'globalDefault' => settings('default_tool_access_level', settings('ai_tools_default_access', 'login_required')),
        ]);
    }

    /**
     * Bulk update access level for multiple templates.
     */
    public function bulkUpdate(Request $request)
    {
        $data = $request->validate([
            'template_ids' => 'required|array',
            'template_ids.*' => 'exists:ai_templates,id',
            'access_level' => 'required|in:inherit,public,login_required,free_plan,pro_plan',
        ]);

        $templates = AiTemplate::whereIn('id', $data['template_ids'])->get();

        AiTemplate::whereIn('id', $data['template_ids'])
            ->update(['access_level' => $data['access_level']]);

        $templates->each(fn (AiTemplate $template) => ToolCatalogCacheService::invalidateForTool($template));

        return back()->with('success', translate('Access levels updated successfully.'));
    }

    /**
     * Update access level for an entire category.
     */
    public function categoryUpdate(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:ai_tool_categories,id',
            'access_level' => 'required|in:inherit,public,login_required,free_plan,pro_plan',
        ]);

        $category = AiToolCategory::findOrFail($data['category_id']);
        $templates = AiTemplate::where('category_id', $category->id)->get();

        AiTemplate::where('category_id', $category->id)
            ->update(['access_level' => $data['access_level']]);

        ToolCatalogCacheService::invalidateForCategory($category);
        $templates->each(fn (AiTemplate $template) => ToolCatalogCacheService::invalidateForTool($template));

        return back()->with('success', translate('Category access levels updated successfully.'));
    }

    /**
     * Apply a quick preset.
     */
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

        AiTemplate::query()->update(['access_level' => $level]);
        ToolCatalogCacheService::invalidateAll();

        return back()->with('success', translate('Preset applied successfully to all tools.'));
    }
}
