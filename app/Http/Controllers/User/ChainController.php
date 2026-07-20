<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\RunToolChainJob;
use App\Models\AiTool;
use App\Models\ToolChain;
use App\Models\ToolChainRun;
use App\Services\AI\TokenGuard;
use App\Services\AI\ToolAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ChainController extends Controller
{
    /**
     * How much text a chain may be started with.
     */
    private const MAX_INPUT_CHARS = 10000;

    public function index(): Response
    {
        $chains = ToolChain::where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->get();

        return Inertia::render('User/Chains/Index', [
            'chains' => $chains,
            'runs' => $this->recentRuns(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('User/Chains/Builder', ['tools' => $this->availableTools()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->stepRules());

        ToolChain::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'steps' => $this->normalizeSteps($validated['steps']),
        ]);

        return redirect()->route('user.dashboard.chains.index');
    }

    public function show(ToolChain $chain): Response
    {
        if ($chain->user_id !== Auth::id()) {
            abort(403);
        }

        return Inertia::render('User/Chains/Builder', [
            'chain' => $chain,
            'tools' => $this->availableTools(),
            'editing' => true,
        ]);
    }

    public function update(Request $request, ToolChain $chain): RedirectResponse
    {
        if ($chain->user_id !== Auth::id()) {
            abort(403);
        }

        // The old rules validated `steps` as a bare array, so update() could persist
        // steps with no tool_slug that store() would have rejected.
        $validated = $request->validate($this->stepRules());

        $chain->update([
            'name' => $validated['name'],
            'steps' => $this->normalizeSteps($validated['steps']),
        ]);

        return redirect()->route('user.dashboard.chains.index');
    }

    public function destroy(ToolChain $chain): RedirectResponse
    {
        if ($chain->user_id !== Auth::id()) {
            abort(403);
        }

        $chain->delete();

        return back();
    }

    public function run(Request $request, ToolChain $chain): RedirectResponse
    {
        if ($chain->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'input' => 'nullable|string|max:'.self::MAX_INPUT_CHARS,
        ]);

        if (empty($chain->steps) || count($chain->steps) < 2) {
            return back()->with('error', translate('A chain needs at least 2 steps to run.'));
        }

        $access = app(ToolAccessService::class);

        foreach ($chain->steps as $step) {
            $number = $step['step'] ?? 0;
            $slug = $step['tool_slug'] ?? '';

            if (empty($slug)) {
                return back()->with('error', translate('Step :step has no tool selected.', ['step' => $number]));
            }

            $tool = AiTool::where('slug', $slug)->where('is_active', true)->first();

            if (! $tool) {
                return back()->with('error', translate("Tool ':slug' in step :step was not found. It may have been deleted.", ['slug' => $slug, 'step' => $number]));
            }

            // Enforce the tool's access level for the chain owner — chains must not
            // be a back door around pro/plan gating.
            if (! $access->checkAccess($tool, Auth::user())->allowed) {
                return back()->with('error', translate("You do not have access to the ':tool' tool used in step :step.", ['tool' => $tool->name, 'step' => $number]));
            }
        }

        // Pre-flight before queueing. TokenGuard::before is mode-aware (in quota mode it
        // gates on the daily/monthly allowance and ignores the un-refillable wallet; in
        // metered mode it also checks the balance), so a user who cannot pay for the
        // first step gets told now instead of watching a queued run fail later.
        try {
            TokenGuard::before(Auth::user(), null, null);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        RunToolChainJob::dispatch($chain, Auth::user(), (string) ($validated['input'] ?? ''));

        return back()->with('success', translate('Chain started — output will appear in Recent runs.'));
    }

    /**
     * Steps carry the two things that make a chain do anything: `static_inputs`
     * (fixed field values) and `field_map` (field values templated from {{input}},
     * {{previous_output}} or {{step_N_output}}). Both must survive validation or the
     * tool runs with no fields at all.
     */
    private function stepRules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'steps' => 'required|array|min:2|max:5',
            'steps.*.tool_slug' => 'required|string|max:100|exists:ai_tools,slug',
            'steps.*.static_inputs' => 'nullable|array',
            'steps.*.static_inputs.*' => 'nullable|string|max:5000',
            'steps.*.field_map' => 'nullable|array',
            'steps.*.field_map.*' => 'nullable|string|max:5000',
        ];
    }

    private function normalizeSteps(array $steps): array
    {
        $normalized = [];

        foreach (array_values($steps) as $index => $step) {
            $normalized[] = [
                'step' => $index + 1,
                'tool_slug' => $step['tool_slug'],
                'static_inputs' => array_filter($step['static_inputs'] ?? [], fn ($v) => $v !== null && $v !== ''),
                'field_map' => array_filter($step['field_map'] ?? [], fn ($v) => $v !== null && $v !== ''),
            ];
        }

        return $normalized;
    }

    /**
     * Tools the user may actually put in a chain, with their field definitions —
     * the builder needs the fields to offer a source for each one.
     */
    private function availableTools()
    {
        $access = app(ToolAccessService::class);
        $user = Auth::user();

        // Full models, not a column subset: checkAccess() reads the tool's access
        // level, and a partial select would leave it null and skew the gate.
        return AiTool::active()
            ->get()
            ->filter(fn (AiTool $tool) => $access->checkAccess($tool, $user)->allowed)
            ->map(fn (AiTool $tool) => [
                'slug' => $tool->slug,
                'name' => $tool->name,
                'fields' => collect($tool->fields ?? [])
                    ->filter(fn ($f) => is_array($f) && ! empty($f['key'] ?? $f['name'] ?? null))
                    ->map(fn ($f) => [
                        'key' => $f['key'] ?? $f['name'],
                        'label' => $f['label'] ?? ($f['key'] ?? $f['name']),
                        'type' => $f['type'] ?? 'text',
                        'required' => (bool) ($f['required'] ?? false),
                        'options' => array_values($f['options'] ?? []),
                        'default' => $f['default'] ?? null,
                    ])
                    ->values(),
            ])
            ->values();
    }

    private function recentRuns()
    {
        return ToolChainRun::with('chain:id,ulid,name')
            ->where('user_id', Auth::id())
            ->latest()
            ->limit(10)
            ->get(['id', 'ulid', 'chain_id', 'status', 'input', 'step_outputs', 'total_tokens', 'total_credits', 'error', 'created_at', 'completed_at']);
    }
}
