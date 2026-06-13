<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\Setting;
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

        $chatbotSettings = null;
        if ($template->slug === 'ai-chatbot') {
            $chatbotSettings = Setting::getByGroup('chatbot');
        }

        $platformSettings = null;
        if ($template->slug === 'social-media-manager') {
            $platformSettings = settings('template_social_platforms', []);
        }

        $stageSettings = null;
        if ($template->slug === 'marketing-suite') {
            $stageSettings = settings('template_marketing_stages', []);
        }

        $chatModels = [];
        $providerRegistry = app(\App\Services\AI\ProviderRegistry::class);
        foreach ($providerRegistry->getEnabledProviders() as $name => $models) {
            $label = config("ai.providers.{$name}.name", ucfirst($name));
            foreach ($models as $model) {
                $chatModels[$model] = $label . ' — ' . $model;
            }
        }

        return Inertia::render('Admin/Appearance/SiteTemplateEditor', [
            'template' => array_merge($template->toArray(), [
                'bundled_tool_slugs' => $slugs,
            ]),
            'bundled_tools' => $bundledTools,
            'missing_tool_slugs' => array_values($missingSlugs),
            'chatbotSettings' => $chatbotSettings,
            'chatModels' => $chatModels,
            'platformSettings' => $platformSettings,
            'stageSettings' => $stageSettings,
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

        return redirect()->route('admin.ai.templates.edit', $template)
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

    public function saveChatbotSettings(Request $request, SiteTemplate $template)
    {
        if ($template->slug !== 'ai-chatbot') {
            abort(404);
        }

        $validated = $request->validate([
            'hide_site_header' => 'boolean',
            'hide_site_footer' => 'boolean',
            'allow_guest_messages' => 'boolean',
            'guest_max_messages' => 'integer|min:0|max:100',
            'guest_max_tokens' => 'integer|min:100|max:8000',
            'free_credits_per_message' => 'numeric|min:0',
            'free_max_tokens' => 'integer|min:100|max:16000',
            'free_max_chat_history' => 'integer|min:1|max:500',
            'free_max_file_size_mb' => 'integer|min:0|max:50',
            'pro_credits_per_message' => 'numeric|min:0',
            'pro_max_tokens' => 'integer|min:100|max:16000',
            'pro_max_file_size_mb' => 'integer|min:0|max:100',
            'pro_unlimited_history' => 'boolean',
            'show_token_usage' => 'boolean',
            'show_credits_charged' => 'boolean',
            'allow_model_select' => 'boolean',
            'show_friendly_model_names' => 'boolean',
            'default_chat_model' => 'nullable|string|max:100',
        ]);

        foreach ($validated as $key => $value) {
            settings_set($key, $value, is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : 'string'), 'chatbot');
        }

        return back()->with('success', translate('Chatbot settings saved.'));
    }

    public function savePlatformSettings(Request $request, SiteTemplate $template)
    {
        if ($template->slug !== 'social-media-manager') {
            abort(404);
        }

        $validated = $request->validate([
            'platforms' => 'required|array',
            'platforms.*.slug' => 'required|string',
            'platforms.*.enabled' => 'required|boolean',
            'default_platform' => 'nullable|string|max:50',
        ]);

        settings_set('template_social_platforms', $validated['platforms'], 'json', 'template_social');
        settings_set('template_social_default_platform', $validated['default_platform'] ?? '', 'string', 'template_social');

        return back()->with('success', translate('Platform settings saved.'));
    }

    public function saveStageSettings(Request $request, SiteTemplate $template)
    {
        if ($template->slug !== 'marketing-suite') {
            abort(404);
        }

        $validated = $request->validate([
            'stages' => 'required|array',
            'stages.*.slug' => 'required|string',
            'stages.*.label' => 'required|string|max:100',
            'stages.*.icon' => 'required|string|max:100',
            'default_stage' => 'nullable|string|max:50',
        ]);

        settings_set('template_marketing_stages', $validated['stages'], 'json', 'template_marketing');
        settings_set('template_marketing_default_stage', $validated['default_stage'] ?? 'awareness', 'string', 'template_marketing');

        return back()->with('success', translate('Stage settings saved.'));
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
