<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiTemplate;
use App\Models\AiToolCategory;
use App\Models\ToolReview;
use App\Services\NotificationEventService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Admin AI Template Management — 5-tab editor.
 *
 * Ref: AI_SaaS_Master_Prompt Part 15.19
 * Tabs: Basic, Prompts, Fields, Content, SEO
 */
class AiTemplateController extends Controller
{
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
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        return Inertia::render('Admin/AI/Templates/Index', [
            'templates' => $query->paginate(20)->withQueryString(),
            'categories' => AiToolCategory::orderBy('sort_order')->get(['id', 'name', 'slug']),
            'filters' => $request->only(['category', 'search', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/AI/Templates/Editor', [
            'template' => null,
            'categories' => AiToolCategory::orderBy('sort_order')->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateTemplate($request);
        $data['fields'] = json_encode($this->normalizeFields($data['fields'] ?? []));
        $data['how_it_works'] = isset($data['how_it_works']) ? json_encode($data['how_it_works']) : null;
        $data['usage_examples'] = isset($data['usage_examples']) ? json_encode($data['usage_examples']) : null;
        $data['faq_items'] = isset($data['faq_items']) ? json_encode($data['faq_items']) : null;

        $template = AiTemplate::create($data);

        if ($template->is_active) {
            app(NotificationEventService::class)->newToolLaunched($template);
        }

        return redirect()->route('admin.ai.templates.index')
            ->with('success', translate('Template created successfully.'));
    }

    public function edit(AiTemplate $template)
    {
        $template->load('toolCategory:id,name,slug');

        return Inertia::render('Admin/AI/Templates/Editor', [
            'template' => $template,
            'categories' => AiToolCategory::orderBy('sort_order')->get(['id', 'name', 'slug']),
            'reviews' => ToolReview::where('template_slug', $template->slug)
                ->with('user:id,name,email')
                ->latest()
                ->take(20)
                ->get(),
        ]);
    }

    public function update(Request $request, AiTemplate $template)
    {
        $data = $this->validateTemplate($request, $template->id);
        $data['fields'] = json_encode($this->normalizeFields($data['fields'] ?? []));
        $data['how_it_works'] = isset($data['how_it_works']) ? json_encode($data['how_it_works']) : null;
        $data['usage_examples'] = isset($data['usage_examples']) ? json_encode($data['usage_examples']) : null;
        $data['faq_items'] = isset($data['faq_items']) ? json_encode($data['faq_items']) : null;

        $wasActive = $template->is_active;

        $template->update($data);

        if (! $wasActive && $template->is_active) {
            app(NotificationEventService::class)->newToolLaunched($template);
        }

        return back()->with('success', translate('Template updated successfully.'));
    }

    public function destroy(AiTemplate $template)
    {
        $template->delete();

        return redirect()->route('admin.ai.templates.index')
            ->with('success', translate('Template deleted.'));
    }

    /**
     * Toggle template active status.
     */
    public function toggle(AiTemplate $template)
    {
        $template->update(['is_active' => ! $template->is_active]);

        if ($template->is_active) {
            app(NotificationEventService::class)->newToolLaunched($template);
        }

        return back()->with('success', $template->is_active ? translate('Template activated.') : translate('Template deactivated.'));
    }

    /**
     * Approve/reject a tool review.
     */
    public function reviewAction(Request $request, ToolReview $review)
    {
        $action = $request->validate(['action' => 'required|in:approve,reject,reply'])['action'];

        if ($action === 'approve') {
            $review->update(['is_approved' => true]);
        } elseif ($action === 'reject') {
            $review->update(['is_approved' => false]);
        } elseif ($action === 'reply') {
            $request->validate(['admin_reply' => 'required|string|max:1000']);
            $review->update(['admin_reply' => $request->admin_reply]);
        }

        return back()->with('success', translate('Review :action.', ['action' => $action]));
    }

    private function validateTemplate(Request $request, ?int $ignoreId = null): array
    {
        $slugUnique = $ignoreId ? "unique:ai_templates,slug,{$ignoreId}" : 'unique:ai_templates,slug';

        return $request->validate([
            // Tab 1: Basic
            'name' => 'required|string|max:255',
            'slug' => "required|string|max:100|{$slugUnique}",
            'description' => 'required|string|max:500',
            'category_id' => 'nullable|exists:ai_tool_categories,id',
            'icon' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'requires_pro' => 'boolean',
            'access_level' => 'nullable|in:inherit,public,login_required,free_plan,pro_plan',

            // Tab 2: Prompts
            'prompt_system' => 'nullable|string|max:10000',
            'prompt_user' => 'nullable|string|max:10000',
            'output_type' => 'nullable|in:text,markdown,html,code,list,image,audio,video,json',
            'model_override' => 'nullable|string|max:100',
            'max_tokens_override' => 'nullable|integer|min:1|max:128000',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'supports_brand_voice' => 'boolean',
            'avg_output_tokens' => 'nullable|integer|min:1',

            // Tab 3: Fields
            'fields' => 'nullable|array',
            'fields.*.key' => 'nullable|string|max:100',
            'fields.*.name' => 'nullable|string|max:100',
            'fields.*.label' => 'required_with:fields|string|max:255',
            'fields.*.type' => 'required_with:fields|string|in:text,textarea,select,number,toggle,slider,color,tags_input,tone_select,language_select,length_select,model_select,image_upload,file_upload,code_input,url',
            'fields.*.required' => 'boolean',
            'fields.*.options' => 'nullable|array',
            'fields.*.placeholder' => 'nullable|string|max:255',

            // Tab 4: Content
            'about_content' => 'nullable|string|max:10000',
            'how_it_works' => 'nullable|array',
            'usage_examples' => 'nullable|array',
            'faq_items' => 'nullable|array',
            'show_about' => 'boolean',
            'show_how_it_works' => 'boolean',
            'show_usage_examples' => 'boolean',
            'show_faqs' => 'boolean',
            'show_reviews' => 'boolean',
            'show_related_tools' => 'boolean',

            // Tab 5: SEO
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|string|max:500',
        ]);
    }

    private function normalizeFields(array $fields): array
    {
        return array_values(array_map(function (array $field): array {
            $name = $field['name'] ?? $field['key'] ?? $field['id'] ?? null;

            return array_merge($field, [
                'name' => $name,
                'key' => $name,
            ]);
        }, $fields));
    }
}
