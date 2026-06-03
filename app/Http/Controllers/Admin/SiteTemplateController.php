<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiteTemplateResource;
use App\Models\AiTool;
use App\Models\SiteTemplate;
use App\Services\AI\SiteTemplateCacheService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SiteTemplateController extends Controller
{
    public function __construct(
        private SiteTemplateCacheService $cache,
    ) {}

    public function index()
    {
        $templates = SiteTemplate::orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (SiteTemplate $template) => [
                'id' => $template->id,
                'slug' => $template->slug,
                'name' => $template->name,
                'tagline' => $template->tagline,
                'preview_image' => $template->preview_image,
                'icon' => $template->icon,
                'requires_pro' => (bool) $template->requires_pro,
                'is_active' => (bool) $template->is_active,
                'bundled_tool_count' => count($this->resolveBundledSlugs($template)),
            ]);

        return Inertia::render('Admin/Appearance/SiteTemplates', [
            'templates' => $templates,
        ]);
    }

    public function edit(SiteTemplate $template)
    {
        $slugs = $this->resolveBundledSlugs($template);

        $bundledTools = empty($slugs)
            ? collect()
            : AiTool::query()
                ->whereIn('slug', $slugs)
                ->get(['slug', 'name', 'icon', 'is_active'])
                ->map(fn (AiTool $tool) => [
                    'slug' => $tool->slug,
                    'name' => $tool->name,
                    'icon' => $tool->icon,
                    'is_active' => (bool) $tool->is_active,
                ]);

        $missingSlugs = array_diff($slugs, $bundledTools->pluck('slug')->all());

        return Inertia::render('Admin/Appearance/SiteTemplateEditor', [
            'template' => array_merge($template->toArray(), [
                'bundled_tool_slugs' => $slugs,
            ]),
            'bundled_tools' => $bundledTools,
            'missing_tool_slugs' => array_values($missingSlugs),
        ]);
    }

    public function update(Request $request, SiteTemplate $template)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:100',

            'color_primary' => 'nullable|string|max:20',
            'color_secondary' => 'nullable|string|max:20',
            'color_bg' => 'nullable|string|max:20',
            'color_surface' => 'nullable|string|max:20',
            'color_text' => 'nullable|string|max:20',
            'font_heading' => 'nullable|string|max:100',
            'font_body' => 'nullable|string|max:100',

            'hero_headline' => 'nullable|string|max:500',
            'hero_subheadline' => 'nullable|string|max:2000',
            'hero_cta_text' => 'nullable|string|max:100',
            'hero_cta_url' => 'nullable|string|max:500',

            'custom_html_head' => 'nullable|string|max:50000',
            'custom_html_body' => 'nullable|string|max:50000',
            'custom_css' => 'nullable|string|max:50000',

            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ]);

        $template->update($validated);

        return redirect()->route('admin.site-templates.edit', $template)
            ->with('success', translate('Template updated successfully.'));
    }

    public function toggle(SiteTemplate $template)
    {
        $template->update(['is_active' => ! $template->is_active]);

        return back()->with('success', translate('Template status updated.'));
    }

    public function resetToDefaults(SiteTemplate $template)
    {
        $template->update([
            'color_primary' => null,
            'color_secondary' => null,
            'color_bg' => null,
            'color_surface' => null,
            'color_text' => null,
            'font_heading' => null,
            'font_body' => null,
        ]);

        return back()->with('success', translate('Appearance reset to global defaults.'));
    }

    private function resolveBundledSlugs(SiteTemplate $template): array
    {
        $slugs = $template->bundled_tool_slugs;

        if (is_string($slugs)) {
            $slugs = json_decode($slugs, true);
        }

        return is_array($slugs) ? $slugs : [];
    }
}
