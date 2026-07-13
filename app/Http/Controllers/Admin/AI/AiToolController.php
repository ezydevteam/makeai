<?php

namespace App\Http\Controllers\Admin\AI;

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
        $query = AiTool::with('category:id,name,slug,color')
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
            'hasTrashedTools' => AiTool::onlyTrashed()->exists(),
        ]);
    }

    public function trash(Request $request)
    {
        $query = AiTool::onlyTrashed()
            ->with('category:id,name,slug,color')
            ->orderByDesc('deleted_at')
            ->orderBy('name');

        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }
        if ($request->filled('search')) {
            $query->where(function ($builder) use ($request) {
                $builder
                    ->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('slug', 'like', '%'.$request->search.'%');
            });
        }
        if ($request->filled('status') && in_array($request->status, ['active', 'inactive'])) {
            $query->where('is_active', $request->status === 'active');
        }

        return Inertia::render('Admin/AI/Tools/Trash', [
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
            'aiModels' => (function() use ($configuredProviders) {
                $friendlyModelNames = config('ai.model_names', []);
                $friendlyProviderNames = config('ai.provider_names', []);
                return AiModel::active()
                    ->whereIn('provider', $configuredProviders)
                    ->orderBy('provider')
                    ->orderBy('name')
                    ->get(['slug', 'name', 'provider'])
                    ->map(fn ($m) => [
                        'slug' => $m->slug,
                        'name' => $friendlyModelNames[$m->slug] ?? $m->name,
                        'provider' => $friendlyProviderNames[$m->provider] ?? ucfirst($m->provider),
                    ]);
            })(),
            'accessLevels' => app(\App\Services\AccessLevelService::class)->getOptions(),
            'integrations' => \App\Services\AI\UtilityToolRunner::availableIntegrations(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateTool($request);
        $data['is_system'] = false;

        if (($request->type ?? 'template') !== 'rag') {
            $data['fields'] = $this->normalizeFields($data['fields'] ?? []);
        }

        $data = $this->processUsageExamples($data);

        if ($request->hasFile('og_image_file')) {
            $data['og_image'] = store_public_upload($request->file('og_image_file'), 'ai-tools');
        }

        $tool = AiTool::create($data);

        if ($tool->is_active) {
            app(NotificationEventService::class)->newToolLaunched($tool);
        }

        return redirect()->route('admin.ai.tools.index')
            ->with('success', translate('Tool created successfully.'));
    }

    public function edit(string $tool)
    {
        $tool = $this->findToolOrFail($tool);
        $tool->load('category:id,name,slug');
        $configuredProviders = AiKey::available()->distinct()->pluck('provider')->toArray();

        return Inertia::render('Admin/AI/Tools/Editor', [
            'tool' => $tool,
            'categories' => Category::orderBy('sort_order')->get(['id', 'name', 'slug']),
            'aiModels' => (function() use ($configuredProviders) {
                $friendlyModelNames = config('ai.model_names', []);
                $friendlyProviderNames = config('ai.provider_names', []);
                return AiModel::active()
                    ->whereIn('provider', $configuredProviders)
                    ->orderBy('provider')
                    ->orderBy('name')
                    ->get(['slug', 'name', 'provider'])
                    ->map(fn ($m) => [
                        'slug' => $m->slug,
                        'name' => $friendlyModelNames[$m->slug] ?? $m->name,
                        'provider' => $friendlyProviderNames[$m->provider] ?? ucfirst($m->provider),
                    ]);
            })(),
            'reviews' => ToolReview::where('tool_slug', $tool->slug)
                ->with('user:id,name,email')
                ->latest()
                ->take(20)
                ->get(),
            'accessLevels' => app(\App\Services\AccessLevelService::class)->getOptions(),
            'integrations' => \App\Services\AI\UtilityToolRunner::availableIntegrations(),
        ]);
    }

    public function update(Request $request, string $tool)
    {
        $tool = $this->findToolOrFail($tool);
        $data = $this->validateTool($request, $tool->id);
        if ($tool->type !== 'rag') {
            $data['fields'] = $this->normalizeFields($data['fields'] ?? []);
        }

        $data = $this->processUsageExamples($data);

        if ($request->hasFile('og_image_file')) {
            $data['og_image'] = store_public_upload($request->file('og_image_file'), 'ai-tools', $tool->og_image);
        }

        $wasActive = $tool->is_active;

        $tool->update($data);

        if (! $wasActive && $tool->is_active) {
            app(NotificationEventService::class)->newToolLaunched($tool);
        }

        return back()->with('success', translate('Tool updated successfully.'));
    }

    public function destroy(string $tool)
    {
        $tool = $this->findToolOrFail($tool);
        $tool->delete();

        return redirect()->route('admin.ai.tools.index')
            ->with('success', translate('Tool moved to trash.'));
    }

    public function restore(string $tool)
    {
        $tool = $this->findToolOrFail($tool, true);
        $tool->restore();

        return back()->with('success', translate('Tool restored.'));
    }

    public function forceDelete(string $tool)
    {
        $tool = $this->findToolOrFail($tool, true);
        $tool->forceDelete();

        return back()->with('success', translate('Tool permanently deleted.'));
    }

    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer'],
            'action' => ['required', 'string', 'in:activate,deactivate,delete'],
        ]);

        $query = AiTool::query()->whereIn('id', $validated['ids']);

        match ($validated['action']) {
            'activate' => $query->update(['is_active' => true]),
            'deactivate' => $query->update(['is_active' => false]),
            'delete' => $query->delete(),
        };

        return back()->with('success', translate('Bulk action completed.'));
    }

    public function bulkTrash(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer'],
            'action' => ['required', 'string', 'in:restore,force_delete'],
        ]);

        // Permanent deletion is irreversible — Super Admins only.
        if ($validated['action'] === 'force_delete' && ! auth('admin')->user()->isSuperAdmin()) {
            abort(403, translate('This action is restricted to Super Admins.'));
        }

        $query = AiTool::onlyTrashed()->whereIn('id', $validated['ids']);

        if ($validated['action'] === 'restore') {
            $query->restore();
        }

        if ($validated['action'] === 'force_delete') {
            $query->forceDelete();
        }

        return back()->with('success', translate('Bulk action completed.'));
    }

    /**
     * Toggle tool active status.
     */
    public function toggle(string $tool)
    {
        $tool = $this->findToolOrFail($tool);
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

        $type = $request->input('type');
        $existingTool = null;

        if (! $type && $ignoreId) {
            $existingTool = AiTool::withTrashed()->find($ignoreId);
            $type = $existingTool?->type;
        } elseif ($ignoreId) {
            $existingTool = AiTool::withTrashed()->find($ignoreId);
        }

        $rules = [
            // Tab 1: Basic
            'type' => 'nullable|in:ai_tool,rag',
            'name' => 'required|string|max:255',
            'slug' => "required|string|max:100|{$slugUnique}",
            'description' => 'required|string|max:500',
            'category_id' => 'nullable|exists:categories,id',
            'icon' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'show_header' => 'boolean',
            'show_footer' => 'boolean',
            'access_level' => ['nullable', ...app(\App\Services\AccessLevelService::class)->getValidationRules()],
            'is_embeddable' => 'boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:100',

            // Tab 2: Prompts
            'prompt_system' => 'nullable|string|max:10000',
            'prompt_user' => 'nullable|string|max:10000',
            'output_type' => 'nullable|in:text,markdown,html,code,list,image,audio,video,json',
            'model_override' => 'nullable|string|max:100',
            'generation_mode' => 'nullable|in:llm,integration,integration_llm_fallback',
            'integration_slug' => 'nullable|string|max:60',
            'max_tokens_override' => 'nullable|integer|min:1|max:128000',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'supports_brand_voice' => 'boolean',
            'max_variants' => 'nullable|integer|min:1|max:5',
            'avg_output_tokens' => 'nullable|integer|min:1',

            // Tab 3: Fields
            'fields' => 'nullable|array',
        ];

        if ($type !== 'rag') {
            $rules += [
                'fields.*.id' => 'nullable|string|max:100',
                'fields.*.key' => 'nullable|string|max:100',
                'fields.*.name' => 'nullable|string|max:100',
                'fields.*.label' => 'required_with:fields|string|max:255',
                'fields.*.type' => 'required_with:fields|string|in:text,textarea,select,number,toggle,slider,color,tags_input,tone_select,language_select,length_select,model_select,image_upload,file_upload,code_input,url,date,datetime_local,radio,multi_select,hidden,audience_select',
                'fields.*.required' => 'boolean',
                'fields.*.options' => 'nullable|array',
                'fields.*.placeholder' => 'nullable|string|max:255',
                'fields.*.default' => 'nullable',
                'fields.*.min' => 'nullable|string|max:20',
                'fields.*.max' => 'nullable|string|max:20',
                'fields.*.step' => 'nullable|string|max:20',
                'fields.*.rows' => 'nullable|integer|min:1|max:50',
                'fields.*.max_length' => 'nullable|integer|min:1',
            ];
        }

        $rules += [
            // Tab 4: Content
            'about_content' => 'nullable|string|max:10000',
            'how_it_works' => 'nullable|array',
            'how_it_works.*.title' => 'required_with:how_it_works|string|max:255',
            'how_it_works.*.description' => 'required_with:how_it_works|string|max:2000',
            'how_it_works.*.icon' => 'nullable|string|max:100',
            'how_it_works.*.step' => 'nullable|integer|min:1',
            'usage_examples' => 'nullable|array',
            'usage_examples.*.title' => 'required_with:usage_examples|string|max:255',
            'usage_examples.*.input_text' => 'nullable|string|max:5000',
            'usage_examples.*.output' => 'required_with:usage_examples|string|max:5000',
            'faq_items' => 'nullable|array',
            'faq_items.*.question' => 'required_with:faq_items|string|max:500',
            'faq_items.*.answer' => 'required_with:faq_items|string|max:5000',
            'show_about' => 'boolean',
            'show_how_it_works' => 'boolean',
            'show_usage_examples' => 'boolean',
            'show_faqs' => 'boolean',
            'show_reviews' => 'boolean',
            'show_related_tools' => 'boolean',
            'show_improve' => 'boolean',

            // Tab 5: SEO
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|string|max:500',
            'og_image_file' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
        ];

        $validated = $request->validate($rules);

        // Validate that prompt placeholders match defined fields
        if ($type !== 'rag') {
            $this->validatePromptPlaceholders($request, $validated);
        }

        $validated['avg_output_tokens'] = $validated['avg_output_tokens']
            ?? $existingTool?->avg_output_tokens
            ?? 400;

        return $validated;
    }

    /**
     * Validate that placeholders in prompts have corresponding field definitions.
     * Logs warnings for unresolved placeholders but doesn't block saving.
     */
    private function validatePromptPlaceholders(Request $request, array $validated): void
    {
        $promptSystem = $request->input('prompt_system', '');
        $promptUser = $request->input('prompt_user', '');
        $fields = $validated['fields'] ?? [];

        // Collect defined field keys/names
        $definedKeys = collect($fields)->pluck('key')->filter()->merge(
            collect($fields)->pluck('name')->filter()
        )->unique()->toArray();

        // Built-in placeholders that don't need field definitions
        $builtInPlaceholders = ['model', 'length', 'creativity', 'refine_content', 'refine_instruction'];

        // Extract placeholders from prompts
        $allPrompts = $promptSystem . ' ' . $promptUser;
        preg_match_all('/\{\{?([a-z_][a-z0-9_]*)\}\}?/i', $allPrompts, $matches);
        $placeholders = array_unique($matches[1] ?? []);

        $missing = [];
        foreach ($placeholders as $placeholder) {
            if (in_array($placeholder, $builtInPlaceholders, true)) {
                continue;
            }
            if (! in_array($placeholder, $definedKeys, true)) {
                $missing[] = $placeholder;
            }
        }

        if (! empty($missing)) {
            \Log::warning('AiToolController: prompt contains placeholders without matching fields', [
                'tool_name' => $request->input('name'),
                'missing_placeholders' => $missing,
                'defined_fields' => $definedKeys,
            ]);
        }
    }

    private function normalizeFields(array $fields): array
    {
        return array_values(array_map(function (array $field): array {
            $key = $field['key'] ?? $field['name'] ?? $field['id'] ?? null;
            $name = $field['name'] ?? $key;
            unset($field['options_string']);

            return array_merge($field, [
                'name' => $name,
                'key' => $key,
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

    private function processUsageExamples(array $data): array
    {
        if (isset($data['usage_examples']) && is_array($data['usage_examples'])) {
            foreach ($data['usage_examples'] as $i => $example) {
                $inputText = $example['input_text'] ?? '';
                $data['usage_examples'][$i]['input'] = $this->parseExampleInputText($inputText);
                unset($data['usage_examples'][$i]['input_text']);
            }
        }
        return $data;
    }

    private function parseExampleInputText(?string $text): array
    {
        if (empty($text)) {
            return [];
        }

        $input = [];
        $lines = preg_split('/\r\n|\r|\n/', $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Match key: value format by splitting at the first colon
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                
                // Normalize key: lowercase, replace spaces/hyphens with underscores, remove non-alphanumeric/underscore
                $key = strtolower($key);
                $key = preg_replace('/[\s-]+/', '_', $key);
                $key = preg_replace('/[^a-z0-9_]/', '', $key);

                $val = trim($parts[1]);
                $input[$key] = $val;
            }
        }

        return $input;
    }

    private function findToolOrFail(string $tool, bool $withTrashed = false): AiTool
    {
        $query = AiTool::query();

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query
            ->where(function ($builder) use ($tool) {
                $builder->where('ulid', $tool)
                    ->orWhere('slug', $tool);

                if (ctype_digit($tool)) {
                    $builder->orWhere('id', (int) $tool);
                }
            })
            ->firstOrFail();
    }
}
