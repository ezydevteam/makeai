<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiKey;
use App\Models\AiModel;
use App\Models\AiTool;
use App\Models\Category;
use App\Models\ToolReview;
use App\Services\AI\ProviderRegistry;
use App\Services\NotificationEventService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Admin AI Tool Management — 5-tab editor.
 *
 * Ref: AI_SaaS_Master_Prompt Part 14
 * Tabs: Basic, Prompts, Fields, Content, SEO
 */
class AiToolController extends Controller
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
        if ($request->filled('status') && in_array($request->status, ['active', 'inactive'])) {
            $query->where('is_active', $request->status === 'active');
        }

        return Inertia::render('Admin/AI/Tools/Index', [
            'tools' => $query->paginate(20)->withQueryString(),
            'categories' => Category::orderBy('sort_order')->get(['id', 'name', 'slug']),
            'filters' => $request->only(['category', 'search', 'status']),
        ]);
    }

    public function create()
    {
        $configuredProviders = AiKey::available()->distinct()->pluck('provider')->toArray();

        return Inertia::render('Admin/AI/Tools/Editor', [
            'tool' => null,
            'categories' => Category::orderBy('sort_order')->get(['id', 'name', 'slug']),
            'aiModels' => AiModel::active()
                ->whereIn('provider', $configuredProviders)
                ->orderBy('provider')
                ->orderBy('name')
                ->get(['slug', 'name', 'provider']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateTool($request);
        $data['fields'] = $this->normalizeFields($data['fields'] ?? []);

        if ($request->hasFile('og_image_file')) {
            $data['og_image'] = $request->file('og_image_file')->store('ai-tools', 'public');
        }

        $tool = AiTool::create($data);

        if ($tool->is_active) {
            app(NotificationEventService::class)->newToolLaunched($tool);
        }

        return redirect()->route('admin.ai.tools.index')
            ->with('success', translate('Tool created successfully.'));
    }

    public function edit(AiTool $tool)
    {
        $tool->load('category:id,name,slug');
        $configuredProviders = AiKey::available()->distinct()->pluck('provider')->toArray();

        return Inertia::render('Admin/AI/Tools/Editor', [
            'tool' => $tool,
            'categories' => Category::orderBy('sort_order')->get(['id', 'name', 'slug']),
            'aiModels' => AiModel::active()
                ->whereIn('provider', $configuredProviders)
                ->orderBy('provider')
                ->orderBy('name')
                ->get(['slug', 'name', 'provider']),
            'reviews' => ToolReview::where('tool_slug', $tool->slug)
                ->with('user:id,name,email')
                ->latest()
                ->take(20)
                ->get(),
        ]);
    }

    public function update(Request $request, AiTool $tool)
    {
        $data = $this->validateTool($request, $tool->id);
        $data['fields'] = $this->normalizeFields($data['fields'] ?? []);

        if ($request->hasFile('og_image_file')) {
            $data['og_image'] = $request->file('og_image_file')->store('ai-tools', 'public');
        }

        $wasActive = $tool->is_active;

        $tool->update($data);

        if (! $wasActive && $tool->is_active) {
            app(NotificationEventService::class)->newToolLaunched($tool);
        }

        return back()->with('success', translate('Tool updated successfully.'));
    }

    public function destroy(AiTool $tool)
    {
        if ($tool->isSystem()) {
            return back()->with('error', translate('System tools cannot be deleted.'));
        }

        $tool->delete();

        return redirect()->route('admin.ai.tools.index')
            ->with('success', translate('Tool deleted.'));
    }

    /**
     * Toggle tool active status.
     */
    public function toggle(AiTool $tool)
    {
        $tool->update(['is_active' => ! $tool->is_active]);

        if ($tool->is_active) {
            app(NotificationEventService::class)->newToolLaunched($tool);
        }

        return back()->with('success', $tool->is_active ? translate('Tool activated.') : translate('Tool deactivated.'));
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

    private function validateTool(Request $request, ?int $ignoreId = null): array
    {
        $slugUnique = $ignoreId ? "unique:ai_tools,slug,{$ignoreId}" : 'unique:ai_tools,slug';

        return $request->validate([
            // Tab 1: Basic
            'name' => 'required|string|max:255',
            'slug' => "required|string|max:100|{$slugUnique}",
            'description' => 'required|string|max:500',
            'category_id' => 'nullable|exists:categories,id',
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
            'fields.*.id' => 'nullable|string|max:100',
            'fields.*.key' => 'nullable|string|max:100',
            'fields.*.name' => 'nullable|string|max:100',
            'fields.*.label' => 'required_with:fields|string|max:255',
            'fields.*.type' => 'required_with:fields|string|in:text,textarea,select,number,toggle,slider,color,tags_input,tone_select,language_select,length_select,model_select,image_upload,file_upload,code_input,url,date,datetime_local,radio,multi_select,hidden',
            'fields.*.required' => 'boolean',
            'fields.*.options' => 'nullable|array',
            'fields.*.placeholder' => 'nullable|string|max:255',
            'fields.*.default' => 'nullable',
            'fields.*.min' => 'nullable|numeric',
            'fields.*.max' => 'nullable|numeric',
            'fields.*.step' => 'nullable|numeric|min:0',
            'fields.*.rows' => 'nullable|integer|min:1|max:50',
            'fields.*.max_length' => 'nullable|integer|min:1',

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
            'og_image_file' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
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

    private function normalizeJsonArray(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        return array_values($data);
    }
}
